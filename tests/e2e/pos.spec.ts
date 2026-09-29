import { expect, test } from '@playwright/test';

/**
 * Real-browser admin POS journey, on top of (not a replacement for)
 * tests/Feature/E2E/AdminPosShiftJourneyTest.php's HTTP-level version. Uses the local-only
 * /admin/dev-login shortcut (Phase 12 sub-step 3) — this route only exists at all when
 * APP_ENV=local, matching this project's real dev environment.
 */
test('an admin can open the register, ring up a real PKR sale, and close the shift', async ({ page }) => {
  await page.goto('/admin/dev-login');
  await expect(page).toHaveURL(/\/admin$/);

  await page.goto('/admin/pos');

  // Open register — the CashCountForm/OpenRegisterModal dialog is portal-rendered, so query by role
  // rather than assuming DOM nesting under a specific container.
  const openButton = page.getByRole('button', { name: 'Open register' });
  if (await openButton.isVisible().catch(() => false)) {
    await openButton.click();
    await page.locator('#opening_float').fill('5000');
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('/admin/pos/register/open')),
      page.getByRole('dialog').getByRole('button', { name: 'Open' }).click(),
    ]);
    // A 302 here is the expected Inertia `back()` redirect (PosRegisterController::open() success
    // path), not a failure — response.ok() wrongly excludes valid 3xx redirects.
    expect(response.status(), `open-register request returned ${response.status()}`).toBeLessThan(400);
    await expect(page.getByRole('dialog')).toBeHidden({ timeout: 10_000 });
  }
  await expect(page.getByText('Register open', { exact: true })).toBeVisible();

  // Ring up a real sale.
  await page.goto('/admin/pos/terminal');
  const firstService = page.locator('button:has(p.text-ink)').first();
  await expect(firstService).toBeVisible();
  await firstService.click();

  // The cart line for the added service appears with a live-priced total.
  await expect(page.getByText('Cart is empty.')).toHaveCount(0);

  const takePaymentButton = page.getByRole('button', { name: 'Take payment' });
  await expect(takePaymentButton).toBeEnabled({ timeout: 10_000 }); // waits out the 400ms pricing debounce + real /admin/pos/apply-coupon round trip
  await takePaymentButton.click();

  const paymentDialog = page.getByRole('dialog', { name: 'Take payment' });
  await expect(paymentDialog).toBeVisible();
  await expect(paymentDialog.getByText(/^Rs\./).first()).toBeVisible(); // real PKR formatting (multiple Rs. figures render: total/remaining/change)

  // Cash payment defaults to the full total already filled in — just confirm it.
  await paymentDialog.getByRole('button', { name: 'Add payment' }).click();
  await paymentDialog.getByRole('button', { name: 'Complete sale' }).click();

  // Receipt — a real Sale row now exists, re-fetched by id (not trusted from the checkout response).
  const receiptDialog = page.getByRole('dialog', { name: 'Receipt' });
  await expect(receiptDialog).toBeVisible({ timeout: 10_000 });
  await expect(receiptDialog.getByText(/^POS-\d{8}-[A-Z0-9]{4}$/)).toBeVisible();
  await receiptDialog.getByRole('button', { name: 'New sale' }).click();

  // Close the shift and verify the Z-report reflects the real sale just rung up. A real user would
  // click the sidebar's "POS" link here, not reload the browser — using an Inertia client-side
  // transition instead of page.goto() also sidesteps a real WebKit-specific hang: a hard reload
  // right after a burst of requests intermittently never completes against PHP's single-threaded
  // `artisan serve` dev server (confirmed via request/response logging — the navigation itself never
  // even got a response). That's a `php artisan serve` + WebKit connection-reuse quirk, not an app
  // bug — production runs PHP-FPM/Nginx (see DEPLOYMENT.md), which handles concurrent requests
  // properly, and a real user's SPA navigation here never triggers a full document reload anyway.
  await page.getByRole('link', { name: 'POS', exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/pos$/);
  await page.getByRole('button', { name: 'Close register' }).click();

  const closeDialog = page.getByRole('dialog');
  const expectedText = await closeDialog.getByText(/^Rs\./).first().innerText();
  const expectedRupees = Number(expectedText.replace(/[^0-9]/g, ''));
  await closeDialog.locator('#denom-10').fill(String(Math.round(expectedRupees / 10)));

  await closeDialog.getByRole('button', { name: 'Close register' }).click();

  // Re-opens as the Z-Report once closed (RegisterCloseDialog swaps its own contents by shift.status).
  await expect(page.getByRole('heading', { name: 'Z-Report' })).toBeVisible({ timeout: 10_000 });
  await expect(page.getByText('Payment method totals')).toBeVisible();
  await expect(page.getByRole('cell', { name: 'cash', exact: true })).toBeVisible();
});
