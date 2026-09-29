import { expect, test } from '@playwright/test';

/**
 * Real-browser coverage for ad hoc task 20's gallery overhaul — on top of `PublicWebsiteTest.php`'s
 * HTTP-level assertions of section grouping/ordering and `GalleryCategoryControllerTest.php`'s
 * admin CRUD/reorder coverage. Exists to verify what those cannot: the real computed grid columns at
 * each breakpoint, that the lightbox genuinely opens on a real click, and that a real admin reorder
 * is reflected on the live public page afterward.
 *
 * Resilient to WHICH categories/images are actually seeded — this project's dev database only has 2
 * real gallery categories at the time of writing, not the 3+ example names in the task spec, so
 * nothing here hardcodes a specific category name existing.
 */
test('the public gallery page renders stacked category sections with a real 4/2-column grid and a working lightbox', async ({
  page,
}) => {
  await page.goto('/gallery');

  await expect(page.getByRole('heading', { name: 'Our Salon Gallery', level: 1 })).toBeVisible();

  // At least one real category section heading (h2) with a real image grid beneath it.
  const firstSectionHeading = page.locator('h2').first();
  await expect(firstSectionHeading).toBeVisible();

  const firstGrid = page.locator('main .grid.grid-cols-2').first();
  const firstImageButton = firstGrid.getByRole('button').first();
  await expect(firstImageButton).toBeVisible();

  // The default Chromium project viewport (1280x720, from Playwright's "Desktop Chrome" device) is
  // already above Tailwind's `md` breakpoint, so desktop's 4 columns is the one to check first.
  const desktopColumns = await firstGrid.evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
  );
  expect(desktopColumns).toBe(4);

  await page.setViewportSize({ width: 390, height: 844 });
  const mobileColumns = await firstGrid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
  expect(mobileColumns).toBe(2);

  // Clicking a photo opens the real shared lightbox, not a dead click target.
  await firstImageButton.click();
  await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toHaveCount(0);
});

test('an admin can create, edit, and delete a gallery category', async ({ page }) => {
  // This project's `php artisan serve` dev server is genuinely single-threaded on Windows (Decision
  // #30) — every request, including the browser's own CSP-violation-report POSTs (a known, separate,
  // pre-existing noise source — §10 #39), is processed strictly one at a time. A page with several
  // real admin round trips can observably take 10-20s+ per request under that serialization, not a
  // bug in this feature's own code (verified directly: the server-side create/update/delete all
  // completed correctly and quickly in isolation via `php artisan tinker` while this was debugged).
  test.setTimeout(90_000);

  await page.goto('/admin/dev-login');
  await page.goto('/admin/gallery');

  const uniqueName = `E2E Category ${Date.now()}`;

  await page.getByRole('button', { name: 'New category' }).first().click();
  const createDialog = page.getByRole('dialog');
  await createDialog.getByLabel('Name').fill(uniqueName);
  await createDialog.getByLabel('Subtitle').fill('Created by a Playwright real-browser test.');
  await createDialog.getByRole('button', { name: 'Create category' }).click();
  await expect(createDialog).toHaveCount(0, { timeout: 30_000 });

  const categoryRow = page.locator('.divide-y > div').filter({ hasText: uniqueName });
  await expect(categoryRow).toBeVisible();
  await expect(categoryRow).toContainText('0 albums');

  await categoryRow.getByRole('button', { name: 'Edit' }).click();
  const editDialog = page.getByRole('dialog');
  await editDialog.getByLabel('Subtitle').fill('Edited by a Playwright real-browser test.');
  await editDialog.getByRole('button', { name: 'Save changes' }).click();
  await expect(editDialog).toHaveCount(0, { timeout: 30_000 });
  await expect(categoryRow).toContainText('Edited by a Playwright real-browser test.');

  // Delete it again — a real e2e-created category must not linger in the shared dev database for
  // later runs to trip over (the same test-data-hygiene lesson as this project's other e2e specs).
  page.once('dialog', (confirmDialog) => void confirmDialog.accept());
  await categoryRow.getByRole('button', { name: `Delete "${uniqueName}"` }).click();
  await expect(page.locator('.divide-y > div').filter({ hasText: uniqueName })).toHaveCount(0, {
    timeout: 30_000,
  });
});

test('reordering categories in admin changes the public /gallery section order, and is fully reversible', async ({
  page,
}) => {
  // Same single-threaded dev-server latency note as the CRUD test above (Decision #30) — this test
  // makes 2 real reorder round trips plus 2 full page loads of /gallery.
  test.setTimeout(90_000);

  await page.goto('/gallery');
  const originalFirstHeading = await page.locator('h2').first().textContent();

  await page.goto('/admin/dev-login');
  await page.goto('/admin/gallery');

  const categoryRows = page.locator('.divide-y > div');
  const categoryCount = await categoryRows.count();
  test.skip(categoryCount < 2, 'needs at least 2 real gallery categories with images to prove reordering');

  const secondCategoryName = await categoryRows.nth(1).locator('p').first().textContent();
  expect(secondCategoryName).toBeTruthy();

  const moveUpButton = page.getByRole('button', { name: `Move "${secondCategoryName}" up` });
  await moveUpButton.click();
  await expect(categoryRows.first()).toContainText(secondCategoryName!, { timeout: 30_000 });

  await page.goto('/gallery');
  await expect(page.locator('h2').first()).toHaveText(secondCategoryName!);

  // Restore the original order exactly, so this test leaves the shared dev database unchanged.
  await page.goto('/admin/gallery');
  await page.getByRole('button', { name: `Move "${secondCategoryName}" down` }).click();
  await expect(categoryRows.nth(1)).toContainText(secondCategoryName!, { timeout: 30_000 });

  await page.goto('/gallery');
  await expect(page.locator('h2').first()).toHaveText(originalFirstHeading!);
});
