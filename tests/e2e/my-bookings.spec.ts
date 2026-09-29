import { expect, test } from '@playwright/test';

/**
 * Real-browser coverage for the new "My Bookings" customer dashboard page and the cancellation
 * fee-warning modal — on top of `tests/Feature/CustomerDashboardTest.php`'s HTTP-level assertions
 * of the same `can_cancel`/`within_cancellation_fee_window` logic.
 *
 * Registers a brand-new, genuinely unique customer via the real `/register` form rather than
 * relying on a known seeded account (the 80 seeded customers have random faker credentials this
 * test has no way to know), then books a real appointment as that authenticated customer and
 * verifies it end to end on the dedicated page.
 */
test('a signed-in customer sees their real booking on /my-bookings and can cancel it (outside the fee window, no warning shown)', async ({
  page,
}) => {
  // This journey does genuinely more real work than any other single e2e test in the suite —
  // registration (with its own real dispatched-email delay), THEN a full booking (with its own
  // up-to-7-iteration/10s-each date-search retry loop, deliberately starting 2 days out rather than
  // today so the picked slot is guaranteed outside the 24h cancellation-fee window), THEN a
  // cancellation. The project-wide 30s default (`playwright.config.ts`) is tuned for single-purpose
  // tests and was genuinely too tight for this one once real booking-slot contention increased as
  // the e2e suite grew — not a product bug, a test-timeout-budget bug.
  test.setTimeout(75_000);

  const stamp = Date.now();
  const email = `playwright-mybookings-${stamp}@example.test`;

  await page.goto('/register');
  await page.locator('#name').fill('Playwright My Bookings Customer');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill('a-real-strong-password-1');
  await page.locator('#password_confirmation').fill('a-real-strong-password-1');
  await page.getByRole('button', { name: /register|sign up|create account/i }).click();
  // Registration genuinely logs the account in and redirects to /my-account — this can take a few
  // real seconds (a verification email is dispatched synchronously in this environment), so wait
  // for the real navigation rather than a fixed sleep before trusting the session exists.
  await page.waitForURL('**/my-account', { timeout: 20_000 });

  // Book a real appointment as this now-authenticated customer — name/email are pre-filled from
  // the real session (`authUser` prop), proving the account is genuinely logged in. Each service
  // card has its own "+ Add" button (the card itself isn't clickable); the primary CTA lives inside
  // the sticky side panel, not at the bottom of the page.
  await page.goto('/book');
  await page.getByRole('button', { name: /: Add$/ }).first().click();
  await page
    .locator('aside', { hasText: 'Your selection' })
    .getByRole('button', { name: 'Continue' })
    .click();

  const dateInput = page.locator('#booking-date');
  await expect(dateInput).toBeVisible({ timeout: 10_000 });

  const slotButton = page.getByRole('button', { name: /\d{1,2}:\d{2}\s?(AM|PM)/i }).first();
  const noTimesMessage = page.getByText('No times found for this date.');
  async function waitForSlotsToSettle() {
    await Promise.race([
      slotButton.waitFor({ state: 'visible', timeout: 10_000 }),
      noTimesMessage.waitFor({ state: 'visible', timeout: 10_000 }),
    ]).catch(() => {});
  }
  // Start 2 days out and only search forward — this test specifically needs a slot GUARANTEED
  // outside the 24h cancellation-fee window later on, whereas booking.spec.ts's own general "any
  // bookable slot" test starts from today since it has no such requirement.
  let found = false;
  for (let offset = 2; offset < 9 && !found; offset += 1) {
    const target = new Date();
    target.setDate(target.getDate() + offset);
    await dateInput.fill(target.toISOString().slice(0, 10));
    await waitForSlotsToSettle();
    found = await slotButton.isVisible().catch(() => false);
  }
  expect(found).toBe(true);
  await slotButton.click();

  await expect(page.locator('#booking-name')).toHaveValue('Playwright My Bookings Customer');
  await expect(page.locator('#booking-email')).toHaveValue(email);
  await page.getByRole('button', { name: 'Confirm Booking' }).click();
  await expect(page.getByRole('heading', { name: /all booked/i })).toBeVisible({ timeout: 15_000 });

  // Follow the confirmation screen's own link straight to the real page under test.
  await page.getByRole('link', { name: 'View my bookings' }).click();
  await expect(page).toHaveURL(/\/my-bookings$/);
  await expect(page.getByRole('heading', { name: 'My Bookings' })).toBeVisible();

  const bookingCard = page.locator('div').filter({ hasText: /Confirmed|Pending/ }).first();
  await expect(bookingCard).toBeVisible();
  await expect(page.getByRole('button', { name: 'Cancel booking' }).first()).toBeVisible();

  await page.getByRole('button', { name: 'Cancel booking' }).first().click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByRole('heading', { name: 'Cancel this booking?' })).toBeVisible();

  // Booked several days out (the retry loop above only ever picks a slot beyond "today"), so this
  // is outside the 24h fee window — the warning copy must NOT appear.
  await expect(dialog.getByText(/cancellation charge/i)).toHaveCount(0);
  await expect(dialog.getByRole('button', { name: 'Yes, cancel booking' })).toBeVisible();

  await dialog.getByRole('button', { name: 'Yes, cancel booking' }).click();
  await expect(page.getByRole('dialog')).toHaveCount(0);

  // The status badge reflects the real cancellation with no page reload beyond Inertia's own
  // partial reload of the `bookings` prop.
  await expect(page.getByText('Cancelled')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Cancel booking' })).toHaveCount(0);
});
