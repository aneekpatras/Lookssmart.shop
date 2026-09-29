import { expect, test } from '@playwright/test';

/**
 * Real-browser regression coverage for ad hoc task 25 — a targeted bug-fix pass on top of task 24's
 * overhaul. Each test here guards a genuine, measured bug found while fixing this task (not a
 * hypothetical one): a real mobile AND desktop horizontal-overflow bug on `/book` (both traced to a
 * missing `minmax(0, …)` on its `1fr` grid column), a sticky pill bar that unstuck itself almost
 * immediately because its wrapping `<div>` gave it almost no room to stay stuck, and the cart
 * drawer's new savings/empty-state/footer-link additions.
 */

test('/book has no horizontal overflow on mobile or desktop viewports', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/book');
  await expect
    .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
    .toBeLessThanOrEqual(376);

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.reload();
  await expect
    .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
    .toBeLessThanOrEqual(1441);
});

test('the category pill bar on /book scrolls away naturally rather than staying pinned (ad hoc task 31)', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/book');

  const pillBar = page.getByRole('group', { name: 'Filter services by category' });
  await expect(pillBar).toBeVisible();
  const before = await pillBar.boundingBox();

  await page.mouse.wheel(0, 900);
  await page.waitForTimeout(200);

  // Deliberately no longer `position: sticky` (explicit user request) — a genuinely non-sticky
  // element moves up roughly in step with the scroll distance rather than staying pinned near the
  // header on every scroll position.
  const after = await pillBar.boundingBox();
  expect(before!.y - (after?.y ?? before!.y)).toBeGreaterThan(400);
});

test('the category pill bar has a solid background — cards scrolling underneath do not bleed through', async ({
  page,
}) => {
  await page.goto('/services');
  const pillBar = page.getByRole('group', { name: 'Jump to service category' }).locator('..');
  const bg = await pillBar.evaluate((el) => getComputedStyle(el).backgroundColor);
  // A solid color is reported as plain `rgb(r, g, b)` (3 components, fully opaque, no alpha at
  // all) — a translucent background reports as `rgba(r, g, b, a)` with a real 4th component < 1.
  const components = bg.match(/[\d.]+/g) ?? [];
  const alpha = components.length === 4 ? Number(components[3]) : 1;
  expect(alpha).toBe(1);
});

test('Services and Book search inputs use the professional placeholder copy', async ({ page }) => {
  await page.goto('/services');
  await expect(page.getByPlaceholder('Search services, packages, or treatments...')).toBeVisible();

  await page.goto('/book');
  await expect(page.getByPlaceholder('Search services, packages, or treatments...')).toBeVisible();
});

test('an empty cart drawer offers both Explore Services and View Deals', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: /View cart/ }).first().click();

  const drawer = page.getByRole('dialog');
  await expect(drawer.getByRole('link', { name: 'Explore Services' })).toBeVisible();
  await expect(drawer.getByRole('link', { name: 'View Deals' })).toBeVisible();
});

test('a deal added to the cart shows a strikethrough original price and a real "You Save" total', async ({
  page,
}) => {
  await page.goto('/');
  const offerCard = page.locator('section', { hasText: 'Current offers' }).locator('.rounded-lg.border').first();
  await offerCard.getByRole('button', { name: 'Add to Cart' }).click();

  await page.getByRole('button', { name: /View cart/ }).first().click();
  const drawer = page.getByRole('dialog');

  await expect(drawer.locator('.line-through').first()).toBeVisible();
  await expect(drawer.getByText('You Save')).toBeVisible();
  await expect(drawer.getByRole('link', { name: 'Browse More Services' })).toBeVisible();
});

/**
 * Ad hoc task 26 — a further bug-fix pass, on top of task 25's own fixes. The floating header nav
 * itself (not just the category pill bar task 25 already solidified) was still translucent
 * (`glass` = `bg-white/60` + blur) on these 3 card-dense pages, letting scrolled card text visibly
 * bleed through it — confirmed with a real before/after screenshot comparison, not assumed fixed
 * from the diff alone.
 */
test('the floating header nav is solid (not translucent) on Services/Deals/Book, unlike the rest of the site', async ({
  page,
}, testInfo) => {
  // 4 sequential full page.goto navigations against the local dev server, which is genuinely
  // single-threaded (Decision #30, no PHP_CLI_SERVER_WORKERS on Windows) and can take ~5s per
  // request — comfortably enough to exceed the default 30s budget on its own without any code
  // regression. Production (PHP-FPM) does not have this constraint.
  testInfo.setTimeout(60_000);
  for (const url of ['/services', '/deals', '/book']) {
    await page.goto(url);
    const nav = page.locator('header nav').first();
    const bg = await nav.evaluate((el) => getComputedStyle(el).backgroundColor);
    const components = bg.match(/[\d.]+/g) ?? [];
    const alpha = components.length === 4 ? Number(components[3]) : 1;
    expect(alpha, `${url}'s header nav should be fully opaque`).toBe(1);
  }

  // The rest of the site deliberately KEEPS the translucent glass look (a decorative choice over
  // hero imagery) — this fix must not have silently changed it everywhere.
  await page.goto('/');
  const homeNav = page.locator('header nav').first();
  const homeBg = await homeNav.evaluate((el) => getComputedStyle(el).backgroundColor);
  const homeComponents = homeBg.match(/[\d.]+/g) ?? [];
  const homeAlpha = homeComponents.length === 4 ? Number(homeComponents[3]) : 1;
  expect(homeAlpha).toBeLessThan(1);
});

test('/book never renders two buttons for the same time slot (ad hoc task 26)', async ({ page }) => {
  await page.goto('/book');
  await page.getByRole('button', { name: /: Add$/ }).first().click();
  await page.locator('aside', { hasText: 'Your selection' }).getByRole('button', { name: 'Continue' }).click();

  const dateInput = page.locator('#booking-date');
  await expect(dateInput).toBeVisible({ timeout: 10_000 });
  const slotButtons = page.getByRole('button', { name: /\d{1,2}:\d{2}\s?(AM|PM)/i });

  // Originally guarded against `AvailabilityEngine` returning one slot per qualifying staff member
  // (several staff free at the same time rendered as several identical-looking buttons, e.g. three
  // "10:00 AM"s) — ad hoc task 30's capacity-grid refactor made that class of bug structurally
  // impossible (the engine no longer enumerates staff at all, so every `starts_at` appears exactly
  // once by construction), but the invariant is still worth guarding against a future regression.
  // Resilient to which date actually has availability, matching this suite's own established pattern.
  const noTimesMessage = page.getByText('No times found for this date.');
  let count = 0;
  for (let offset = 0; offset < 8 && count === 0; offset += 1) {
    const target = new Date();
    target.setDate(target.getDate() + offset);
    await dateInput.fill(target.toISOString().slice(0, 10));
    // Wait for the request to actually resolve (either outcome) rather than a fixed delay — the
    // local single-threaded dev server (Decision #30) can take several real seconds per request,
    // and a fixed short wait can catch a date's slots still in flight, legitimately reading as 0
    // now that stale/superseded responses are correctly discarded (ad hoc task 28's race fix).
    await Promise.race([
      slotButtons.first().waitFor({ state: 'visible', timeout: 10_000 }),
      noTimesMessage.waitFor({ state: 'visible', timeout: 10_000 }),
    ]).catch(() => {});
    count = await slotButtons.count();
  }
  expect(count, 'expected at least one bookable slot within the next 8 days').toBeGreaterThan(0);

  const labels = await slotButtons.allTextContents();
  expect(labels.length).toBe(new Set(labels).size);
});

test('on mobile, "Your Selection" renders above the services list rather than requiring a scroll to the bottom', async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/book');

  const selectionBox = await page.locator('aside', { hasText: 'Your selection' }).boundingBox();
  const servicesBox = await page.getByRole('heading', { name: 'Select services' }).boundingBox();
  expect(selectionBox!.y).toBeLessThan(servicesBox!.y);

  // Desktop keeps its existing side-by-side layout — this reorder is mobile-only.
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.reload();
  const selectionBoxDesktop = await page.locator('aside', { hasText: 'Your selection' }).boundingBox();
  const servicesBoxDesktop = await page.getByRole('heading', { name: 'Select services' }).boundingBox();
  expect(selectionBoxDesktop!.x).toBeGreaterThan(servicesBoxDesktop!.x);
});

/**
 * Ad hoc task 27 — a further polish pass on top of task 26. Covers: the mobile header redesign
 * (cart/account moved onto the main bar, the drawer reserved for nav links only), "Your Selection"
 * becoming genuinely non-sticky on mobile (it was already reordered to the top in task 26, but still
 * carried `sticky` — so it stayed pinned on screen rather than scrolling away), and a real overflow
 * bug on the homepage's "Transformations" stat card at narrow phone widths.
 */
test('on mobile, the cart and account icons live on the main header bar, and the drawer holds only nav links', async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/');

  // Reachable without opening the drawer at all.
  await expect(page.getByRole('button', { name: /View cart/ })).toBeVisible();
  await expect(page.getByRole('button', { name: /Log in|Your account/ })).toBeVisible();

  await page.getByRole('button', { name: 'Open menu' }).click();
  const drawer = page.locator('header > div').filter({ hasText: 'Home' }).filter({ hasText: 'Contact' });
  await expect(drawer.getByRole('link', { name: 'Home', exact: true })).toBeVisible();
  await expect(drawer.getByRole('link', { name: 'Contact', exact: true })).toBeVisible();
  await expect(drawer.getByRole('link', { name: 'Book Now' })).toHaveCount(0);
  await expect(drawer.getByRole('button', { name: /View cart/ })).toHaveCount(0);
});

test('on mobile, "Your Selection" scrolls away naturally rather than staying pinned', async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/book');

  const selection = page.locator('aside', { hasText: 'Your selection' });
  const beforeScroll = await selection.boundingBox();
  await page.mouse.wheel(0, 700);
  await page.waitForTimeout(200);
  const afterScroll = await selection.boundingBox();

  // A genuinely non-sticky element moves up roughly in step with the scroll distance — a stuck one
  // would stay at (or very near) its original y position no matter how far the page scrolls.
  expect(beforeScroll!.y - (afterScroll?.y ?? beforeScroll!.y)).toBeGreaterThan(400);

  // The category pill bar (deliberately no longer sticky either, ad hoc task 31) is still present
  // further down the page after scrolling.
  const pillBar = page.getByRole('group', { name: 'Filter services by category' });
  await expect(pillBar).toBeVisible();
});

test('the homepage "Transformations" stat label wraps within its card rather than overflowing at 320px', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 900 });
  await page.goto('/');

  const label = page.locator('dt', { hasText: 'Transformations' });
  const card = label.locator('..');
  await label.scrollIntoViewIfNeeded();

  const labelBox = await label.boundingBox();
  const cardBox = await card.boundingBox();
  expect(labelBox!.x + labelBox!.width).toBeLessThanOrEqual(cardBox!.x + cardBox!.width + 1);
});
