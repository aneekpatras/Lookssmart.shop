import { expect, test } from '@playwright/test';

/**
 * Real-browser regression coverage for ad hoc tasks 31-33, from direct user requests (Roman Urdu):
 * the category pill bar on Services/Deals/Book was reported as unwantedly sticky and should scroll
 * away like ordinary content, and clicking "Claim Offer" gave no visible sign a navigation was
 * happening while also feeling slow.
 *
 * - Sticky removal: `CategoryPillBar` dropped `position: sticky` outright (was `sticky top-24 z-30`).
 * - Navigation feedback: a new `NavigationProgressOverlay`, mounted once in `PublicLayout`, listens
 *   to Inertia's `router.on('start'/'finish')` events and shows a themed `LoadingDots` pulse
 *   (originally a skeleton block grid, then a dimmed/blurred full-page backdrop, both swapped away
 *   per follow-up feedback — task 33 removed the backdrop entirely: no background, no blur, just a
 *   small floating, non-blocking pulse) after a 150ms delay — long enough that a fast/prefetched
 *   navigation never flashes it, short enough that a genuinely slow one always does.
 * - Perceived speed: "Claim Offer" links (`DealCard`, `Home`'s `OfferCard`, `DealDetailsModal`) gained
 *   `prefetch` — Inertia loads `/book` in the background on hover, so a click that follows any real
 *   hover dwell time lands on an already-cached response instead of waiting on a fresh request.
 * - Task 33 fix (a real, reported bug, not a style tweak): `router.on('start', ...)` fires for
 *   `prefetch` visits too, not just real navigations — scrolling with the mouse held still slides
 *   page content UNDER a stationary cursor, so a "Claim Offer" button drifting under the pointer
 *   fires a genuine hover, triggering its `prefetch` and wrongly showing the indicator on every such
 *   scroll. Fixed by reading `visit.prefetch` off the event payload and ignoring prefetch visits.
 */

test('the category pill bar scrolls away naturally on Deals/Services/Book rather than staying pinned', async ({
  page,
}) => {
  for (const url of ['/deals', '/services']) {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(url);

    const pillBar = page.getByRole('group', { name: /Jump to (deal|service) category/ });
    await expect(pillBar).toBeVisible();
    const before = await pillBar.boundingBox();

    await page.mouse.wheel(0, 1000);
    await page.waitForTimeout(250);

    const after = await pillBar.boundingBox();
    // A genuinely non-sticky element moves up roughly in step with the scroll distance — a stuck one
    // would stay at (or very near) its original y position no matter how far the page scrolls.
    expect(before!.y - (after?.y ?? before!.y), `${url}'s pill bar should scroll away, not stay pinned`).toBeGreaterThan(
      400,
    );
  }
});

test('clicking Claim Offer shows a real loading indicator before landing on /book', async ({ page }) => {
  await page.goto('/deals');
  const card = page.locator('[data-carousel-item]').first();
  await expect(card).toBeVisible();
  const claimLink = card.getByRole('link', { name: 'Claim Offer' });

  // `dispatchEvent` (not `.click()`) deliberately skips Playwright's natural hover-then-click, which
  // would trigger the new `prefetch` and could make the navigation resolve too fast to ever cross the
  // overlay's 150ms threshold — this proves the overlay itself works for a genuinely fresh, un-hovered
  // click, the realistic case a keyboard/touch user or a fast mouse click actually hits.
  await claimLink.dispatchEvent('click');

  const overlay = page.getByRole('status', { name: 'Loading next page' });
  await expect(overlay).toBeVisible({ timeout: 5_000 });

  await page.waitForURL(/\/book/, { timeout: 20_000 });
  await expect(overlay).toBeHidden();
});

test('hovering Claim Offer long enough to prefetch makes the click itself land almost instantly', async ({
  page,
}) => {
  const bookRequests: string[] = [];
  page.on('request', (req) => {
    if (req.url().includes('/book?')) bookRequests.push(req.url());
  });

  await page.goto('/deals');
  const card = page.locator('[data-carousel-item]').first();
  await expect(card).toBeVisible();
  const claimLink = card.getByRole('link', { name: 'Claim Offer' });

  await claimLink.hover();
  // Give the prefetch request time to actually resolve — the local dev server is genuinely
  // single-threaded and slow (Decision #30), so this is a generous but realistic settle time.
  await page.waitForTimeout(8_000);
  expect(bookRequests.length, 'the hover should have already prefetched /book once').toBeGreaterThan(0);

  const clickStart = Date.now();
  await claimLink.click();
  await page.waitForURL(/\/book/, { timeout: 10_000 });
  const clickDuration = Date.now() - clickStart;

  // A cached, prefetched response swaps in near-instantly — nowhere close to this dev server's
  // otherwise-typical several-second round trip for a fresh, un-prefetched request.
  expect(clickDuration, 'the click itself should be fast once prefetch has already resolved').toBeLessThan(2_000);
  expect(bookRequests.length, 'the click should reuse the prefetched response, not fire a second request').toBe(1);
});

test('scrolling with the mouse held over a card never shows the loading indicator (ad hoc task 33)', async ({
  page,
}) => {
  await page.goto('/deals');
  const card = page.locator('[data-carousel-item]').first();
  await expect(card).toBeVisible();

  // Position the cursor over a "Claim Offer" button, then scroll the PAGE — content slides under the
  // stationary cursor exactly like a real user scrolling with the wheel while their mouse happens to
  // rest over a card. The browser still fires a genuine `mouseenter` on whatever now sits under the
  // cursor, triggering that Link's `prefetch` — which must NOT be mistaken for a real navigation.
  const firstClaim = card.getByRole('link', { name: 'Claim Offer' });
  const box = await firstClaim.boundingBox();
  await page.mouse.move(box!.x + box!.width / 2, box!.y + box!.height / 2);

  const overlay = page.getByRole('status', { name: 'Loading next page' });
  let overlaySeen = false;
  for (let i = 0; i < 15 && !overlaySeen; i++) {
    await page.mouse.wheel(0, 300);
    await page.waitForTimeout(250);
    overlaySeen = await overlay.isVisible().catch(() => false);
  }

  expect(overlaySeen, 'scrolling should never trigger the navigation indicator').toBe(false);
});

test('the loading indicator has no backdrop, dimming, or blur — just a small floating pulse', async ({ page }) => {
  await page.goto('/deals');
  const card = page.locator('[data-carousel-item]').first();
  await expect(card).toBeVisible();
  const claimLink = card.getByRole('link', { name: 'Claim Offer' });

  await claimLink.dispatchEvent('click');
  const overlay = page.getByRole('status', { name: 'Loading next page' });
  await expect(overlay).toBeVisible({ timeout: 5_000 });

  const bg = await overlay.evaluate((el) => getComputedStyle(el).backgroundColor);
  const backdropFilter = await overlay.evaluate((el) => getComputedStyle(el).backdropFilter);
  expect(bg).toBe('rgba(0, 0, 0, 0)');
  expect(backdropFilter === 'none' || backdropFilter === '').toBe(true);

  await page.waitForURL(/\/book/, { timeout: 20_000 });
});
