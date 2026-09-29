import { expect, test } from '@playwright/test';

/**
 * Real-browser coverage for ad hoc task 24 (Services/Deals/Book UI overhaul) — sticky pill bar +
 * scrollspy, live instant search, the redesigned "Your Selection" sidebar, the removed inline deal
 * pills on /book, and the header cart slide-over drawer. On top of (not a replacement for)
 * `deals-page.spec.ts`/`booking.spec.ts`'s own updated coverage of the same pages.
 */

test('Services: instant client-side search filters the grid with no Enter key and no page reload', async ({
  page,
}) => {
  await page.goto('/services');

  await expect(page.getByRole('button', { name: 'Add to Cart' }).first()).toBeVisible();
  const initialCount = await page.getByRole('button', { name: 'Add to Cart' }).count();
  expect(initialCount).toBeGreaterThan(1);

  await page.getByLabel('Search services').fill('facial');
  // No Enter pressed, no navigation — filtering is instant and local.
  await expect
    .poll(async () => page.getByRole('button', { name: 'Add to Cart' }).count(), { timeout: 3_000 })
    .toBeLessThan(initialCount);
  await expect(page).toHaveURL('/services');
  await expect(page.getByRole('heading', { name: 'Hair Styling & Treatments' })).toHaveCount(0);
});

test('Services: the category pill bar highlights the section currently in view and scrolls on click', async ({
  page,
}) => {
  await page.goto('/services');

  const pillBar = page.getByRole('group', { name: 'Jump to service category' });
  await expect(pillBar).toBeVisible();

  const secondPill = pillBar.getByRole('button').nth(1);
  const label = await secondPill.textContent();
  await secondPill.click();

  const heading = page.getByRole('heading', { name: label!, exact: true });
  await expect(heading).toBeVisible();
  await expect
    .poll(async () => (await heading.boundingBox())?.y ?? -1, { timeout: 5_000 })
    .toBeLessThan(260);
  await expect(secondPill).toHaveAttribute('aria-pressed', 'true');
});

test('/book no longer shows the inline package-deal pills below the heading', async ({ page }) => {
  await page.goto('/book');

  await expect(page.getByRole('heading', { name: 'Book an appointment' })).toBeVisible();
  await expect(page.locator('[aria-label="Active offers"]')).toHaveCount(0);
});

test('/book: "Your Selection" sidebar shows a PRICE column header and a thumbnail per row', async ({
  page,
}) => {
  await page.goto('/book');

  await page.getByRole('button', { name: /: Add$/ }).first().click();

  const summary = page.locator('aside', { hasText: 'Your selection' });
  await expect(summary.getByText('Price', { exact: true })).toBeVisible();
  // The row renders a real image well (thumbnail or icon placeholder) next to the name/price.
  await expect(summary.locator('.bg-accent-50').first()).toBeVisible();
});

test('/book: instant search filters the services list', async ({ page }) => {
  await page.goto('/book');

  await expect(page.getByRole('button', { name: /: Add$/ }).first()).toBeVisible();
  const initialCount = await page.getByRole('button', { name: /: Add$/ }).count();
  await page.getByLabel('Search services').fill('facial');
  await expect
    .poll(async () => page.getByRole('button', { name: /: Add$/ }).count(), { timeout: 3_000 })
    .toBeLessThan(initialCount);
});

test('header cart drawer shows a real subtotal and lets an item be removed', async ({ page }) => {
  await page.goto('/services');
  await page.getByRole('button', { name: 'Add to Cart' }).first().click();
  await expect(page.getByText('added to cart')).toBeVisible();

  await page.getByRole('button', { name: /View cart, 1 item selected/ }).click();
  const drawer = page.getByRole('dialog');
  await expect(drawer.getByText('Subtotal')).toBeVisible();
  await expect(drawer.getByRole('link', { name: 'Continue to Booking' })).toBeVisible();

  await drawer.getByRole('button', { name: /Remove .* from cart/ }).click();
  await expect(drawer.getByText('Nothing added yet')).toBeVisible();
});
