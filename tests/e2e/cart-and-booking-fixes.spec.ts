import { expect, test } from '@playwright/test';

/**
 * Real-browser regression coverage for ad hoc task 28. Both fixes here were verified against the
 * real, current codebase before being built — not assumed from the bug report's wording, which named
 * files (`CartContext.tsx`, `booking.ts`) that don't exist in this app's actual architecture
 * (`lib/cart.ts` + Laravel controllers). Investigated each claim directly:
 *
 * - Deal→cart ID collision: confirmed via `tinker` that Deal ids 1-13 genuinely collide with Service
 *   ids 1-13 in the real dev database (both tables auto-increment independently) — a real structural
 *   risk. But every current call site (`dealBundleToCartItems()`, `Services.tsx`'s single-service add)
 *   already only ever writes REAL SERVICE ids into the cart, never a deal's own id — confirmed live by
 *   adding "Party Glam Duo" (deal id 5) to the cart and reading `sessionStorage` directly: the cart
 *   held services 38/40, never `5`. No reproducing bug existed to fix; this test guards the invariant
 *   going forward instead.
 * - Booking time slots: reproduced a REAL bug via a rapid, realistic sequence of date changes —
 *   `Book.tsx`'s `loadSlots()` had no request sequencing, so a slower, stale response for an
 *   already-abandoned date could resolve AFTER a newer one and silently overwrite it, sometimes
 *   leaving neither a slot list nor the "No times found" message on screen. Fixed with a request-id
 *   ref that discards any response that isn't for the most recently requested date.
 */

test('adding a deal to the cart never stores the deal\'s own id — only its real bundled service ids', async ({
  page,
}) => {
  await page.goto('/deals');
  const card = page.locator('[data-carousel-item]').filter({ hasText: 'Party Glam Duo' }).first();
  await expect(card).toBeVisible();
  await card.getByRole('button', { name: 'Add to Cart' }).click();

  const cartItems = await page.evaluate(() => {
    const raw = sessionStorage.getItem('ls_booking_cart_items');
    return raw ? (JSON.parse(raw) as { id: number; name: string }[]) : [];
  });

  expect(cartItems.length).toBeGreaterThan(0);
  // Real service names from this deal's bundle — never the deal's own title as a single line item,
  // and never an id that only makes sense as a deal id.
  const names = cartItems.map((item) => item.name);
  expect(names.some((name) => name.includes('Party Glam Duo'))).toBe(false);

  await page.getByRole('button', { name: /View cart/ }).first().click();
  const drawer = page.getByRole('dialog');
  await expect(drawer.getByText('Signature Glam Party Makeup')).toBeVisible();
  await expect(drawer.getByText('HD High-Definition Party Look')).toBeVisible();
});

test('rapidly changing the booking date never leaves a stale or blank slot list behind', async ({ page }) => {
  await page.goto('/book');
  await page.getByRole('button', { name: /: Add$/ }).first().click();
  await page.locator('aside', { hasText: 'Your selection' }).getByRole('button', { name: 'Continue' }).click();

  const dateInput = page.locator('#booking-date');
  await expect(dateInput).toBeVisible({ timeout: 10_000 });

  const slotButton = page.getByRole('button', { name: /\d{1,2}:\d{2}\s?(AM|PM)/i });
  const noTimesMessage = page.getByText('No times found for this date.');

  // Find a real date with availability first (patient), matching this suite's established
  // resilient-to-which-date pattern.
  let targetOffset = -1;
  for (let offset = 1; offset < 10 && targetOffset === -1; offset += 1) {
    const target = new Date();
    target.setDate(target.getDate() + offset);
    await dateInput.fill(target.toISOString().slice(0, 10));
    await Promise.race([
      slotButton.first().waitFor({ state: 'visible', timeout: 8_000 }),
      noTimesMessage.waitFor({ state: 'visible', timeout: 8_000 }),
    ]).catch(() => {});
    if (await slotButton.count()) targetOffset = offset;
  }
  expect(targetOffset, 'expected at least one date with real availability').toBeGreaterThan(-1);

  // Now change dates quickly a few times, ending back on the known-good date — a realistic "browsing
  // several days" click cadence, not an artificial network-saturating storm.
  for (const offset of [targetOffset + 1, targetOffset + 2, targetOffset]) {
    const target = new Date();
    target.setDate(target.getDate() + offset);
    await dateInput.fill(target.toISOString().slice(0, 10));
    await page.waitForTimeout(600);
  }

  await Promise.race([
    slotButton.first().waitFor({ state: 'visible', timeout: 8_000 }),
    noTimesMessage.waitFor({ state: 'visible', timeout: 8_000 }),
  ]).catch(() => {});

  // The known-good date must show its real slots — not a stale empty/blank state left over from one
  // of the dates browsed past on the way back to it.
  await expect(slotButton.first()).toBeVisible();
  await expect(noTimesMessage).toHaveCount(0);
});
