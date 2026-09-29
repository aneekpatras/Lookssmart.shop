import { expect, test } from '@playwright/test';

/**
 * Real-browser regression coverage for ad hoc task 29 — a deep re-investigation of 3 previously
 * reported issues, explicitly instructed not to assume any were already fixed. All 3 were re-verified
 * from scratch against the live app and real dev data rather than trusted from any prior summary:
 *
 * - Booking time slots: found a REAL, previously-undiscovered bug distinct from task 28's race-
 *   condition fix. `Book.tsx` computed its default/min date via `new Date().toISOString().slice(0,
 *   10)`, which returns the UTC calendar date, not the browser's LOCAL calendar date. For any
 *   timezone ahead of UTC (this salon is in Lahore, Pakistan, UTC+5) during the ~5-hour daily window
 *   after local midnight but before UTC's date rolls over, the local calendar date is already a day
 *   ahead of the UTC one — so the wizard silently defaulted to a date `AvailabilityEngine` correctly
 *   treats as already in the past, returning zero slots. Fixed with a `localDateString()` helper that
 *   reads `getFullYear()`/`getMonth()`/`getDate()` directly instead of going through UTC.
 * - Add to Cart: re-verified live under harder stress than before (double-click, 5 rapid interleaved
 *   adds mixing a deal and several services) — zero ID collisions, zero duplicate/missing items, cart
 *   remained correct and deterministic throughout. No reproducing bug found; consolidated the 4
 *   previously-duplicated add-to-cart call sites into one shared `addToCart()` in `lib/cart.ts` so
 *   there is now exactly one place this logic can ever drift from "atomic and deterministic."
 * - Sticky masking: found a REAL, visually-confirmed bleed-through bug distinct from every prior
 *   masking fix (tasks 25-27, which all correctly solidified the pill bar and the nav PILL itself).
 *   `PublicLayout.tsx`'s outer `<header>` element — the actual `fixed z-40` positioning box — had NO
 *   background of its own; only its child `<nav>` pill did. The header's `pt-4` top padding and the
 *   horizontal space outside the centered `max-w-5xl` nav pill were fully transparent, letting
 *   scrolled cards paint through at full opacity in that gap — confirmed with a real cropped
 *   screenshot showing card "+Add" buttons peeking in above the nav pill. Fixed by giving the outer
 *   `<header>` itself a solid `bg-ivory` background, scoped to `solidHeader` pages only (Services/
 *   Deals/Book) so the decorative translucent `glass` look elsewhere is untouched.
 */

test('the booking wizard defaults to the real local "today", not a UTC-shifted date with no availability', async ({
  page,
}) => {
  await page.goto('/book');
  await page.getByRole('button', { name: /: Add$/ }).first().click();
  await page.locator('aside', { hasText: 'Your selection' }).getByRole('button', { name: 'Continue' }).click();

  const dateInput = page.locator('#booking-date');
  await expect(dateInput).toBeVisible({ timeout: 10_000 });

  const expectedLocalToday = await page.evaluate(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  });
  await expect(dateInput).toHaveValue(expectedLocalToday);
  await expect(dateInput).toHaveAttribute('min', expectedLocalToday);
});

test('adding a deal and several services rapidly never collides IDs, drops items, or duplicates lines', async ({
  page,
}) => {
  await page.goto('/deals');
  const card = page.locator('[data-carousel-item]').filter({ hasText: 'Party Glam Duo' }).first();
  await expect(card).toBeVisible();
  await card.getByRole('button', { name: 'Add to Cart' }).dblclick();

  await page.goto('/services');
  const addButtons = page.getByRole('button', { name: '+ Add to Cart' });
  const count = await addButtons.count();
  for (let i = 0; i < Math.min(5, count); i++) {
    await addButtons.nth(i).click();
  }
  await page.waitForTimeout(400);

  const cartItems = await page.evaluate(() => {
    const raw = sessionStorage.getItem('ls_booking_cart_items');
    return raw ? (JSON.parse(raw) as { id: number; name: string }[]) : [];
  });

  // 2 real bundled services from the deal + up to 5 distinct services, zero duplicates, zero
  // fabricated/collided ids (a deal's own id, e.g. 5, must never appear as a cart item id).
  const ids = cartItems.map((item) => item.id);
  expect(ids.length).toBe(new Set(ids).size);
  expect(cartItems.length).toBeGreaterThanOrEqual(2 + Math.min(5, count));
  const names = cartItems.map((item) => item.name);
  expect(names.some((name) => name.includes('Party Glam Duo'))).toBe(false);
});

test('the floating header on Services/Deals/Book has zero gap for scrolled cards to bleed through', async ({
  page,
}, testInfo) => {
  // 4 sequential full page navigations against the local dev server, which is genuinely
  // single-threaded (Decision #30) and can take several real seconds per request — comfortably
  // enough to exhaust the default 30s whole-test budget on its own (established pattern, ad hoc
  // task 28's own regression-sweep fixes hit the exact same thing).
  testInfo.setTimeout(60_000);
  for (const url of ['/services', '/deals', '/book']) {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(url);
    await page.waitForTimeout(300);
    await page.evaluate(() => window.scrollTo(0, 700));
    await page.waitForTimeout(300);

    // The header's own bounding box — not just its inner nav pill — must be fully opaque, since the
    // outer box (which spans the full header height/width at z-40) is what actually determines
    // whether scrolled content underneath can show through its own padding/margins.
    const header = page.locator('header').first();
    const bg = await header.evaluate((el) => getComputedStyle(el).backgroundColor);
    const components = bg.match(/[\d.]+/g) ?? [];
    const alpha = components.length === 4 ? Number(components[3]) : 1;
    expect(alpha, `${url}'s outer header box should be fully opaque`).toBe(1);
  }

  // The rest of the site deliberately keeps its decorative translucent header — this fix must not
  // have silently made every page's header solid.
  await page.goto('/');
  const homeHeader = page.locator('header').first();
  const homeBg = await homeHeader.evaluate((el) => getComputedStyle(el).backgroundColor);
  const homeComponents = homeBg.match(/[\d.]+/g) ?? [];
  const homeAlpha = homeComponents.length === 4 ? Number(homeComponents[3]) : 1;
  expect(homeAlpha).toBeLessThan(1);
});
