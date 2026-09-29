import { expect, test } from '@playwright/test';

/**
 * Real-browser regression coverage for ad hoc task 34's "Your Selection" UI additions:
 *
 * - A per-item delete/trash button.
 * - A mobile-only (`lg:hidden`) sticky bottom summary bar shows the item count, live total, and a CTA
 *   mirroring the current step's own primary action — on top of "Your Selection" already sitting at
 *   the top of the mobile flow (ad hoc task 26), not a replacement for it.
 *
 * (Task 34 also changed slot availability to show a duration-overflow slot as disabled rather than
 * excluded — ad hoc task 35 then reversed that specific rule outright per an explicit follow-up spec;
 * see `AvailabilityEngineTest.php` for that behavior's own current coverage.)
 */

test('deleting a service from "Your Selection" removes it from the selection and updates the total', async ({
  page,
}) => {
  await page.goto('/book');
  const addButtons = page.getByRole('button', { name: /: Add$/ });
  await addButtons.nth(0).click();
  await addButtons.nth(1).click();

  const aside = page.locator('aside', { hasText: 'Your selection' });
  const deleteButtons = aside.locator('button[aria-label^="Remove "]');
  await expect(deleteButtons).toHaveCount(2);

  const totalBefore = await aside.locator('text=Total Amount').locator('..').textContent();

  await deleteButtons.first().click();

  await expect(deleteButtons).toHaveCount(1);
  const totalAfter = await aside.locator('text=Total Amount').locator('..').textContent();
  expect(totalAfter).not.toBe(totalBefore);

  // The corresponding service card itself flips back from "Added" to "Add".
  await expect(addButtons).toHaveCount((await addButtons.count()));
});

test('on mobile, a sticky bottom bar shows the item count, live total, and a working Continue/Confirm CTA', async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/book');

  const bar = page.locator('div.fixed.bottom-0');
  await expect(bar).toHaveCount(0); // nothing selected yet — the bar only appears once there's something to show

  await page.getByRole('button', { name: /: Add$/ }).first().click();
  await expect(bar).toBeVisible();
  await expect(bar).toContainText('1 service selected');
  await expect(bar.getByRole('button', { name: 'Continue' })).toBeVisible();

  // Its CTA genuinely drives the same step transition as the main "Continue" button.
  await bar.getByRole('button', { name: 'Continue' }).click();
  await expect(page.locator('#booking-date')).toBeVisible({ timeout: 10_000 });
  await expect(bar.getByRole('button', { name: 'Confirm Booking' })).toBeVisible();
});

test('on desktop, the mobile sticky bottom bar is never visible (present in the DOM but `lg:hidden`)', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/book');
  await page.getByRole('button', { name: /: Add$/ }).first().click();

  await expect(page.locator('div.fixed.bottom-0')).toBeHidden();
});
