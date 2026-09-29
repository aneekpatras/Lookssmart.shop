import { expect, test } from '@playwright/test';

/**
 * Real-browser checks for the Deals & Offers page — section/tab rendering, the native
 * `scrollLeft`-driven carousel (genuine auto-advance, pause-on-hover, manual arrows — not just
 * markup presence), the Details modal, CTA hrefs, theme-token compliance, and responsiveness.
 *
 * Uses real seeded titles/codes from `DealShowcaseSeeder` — never fabricated content.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

test('header banner, filter tabs and category sections render', async ({ page }) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: 'Exclusive Deals & Packages' })).toBeVisible();
  await expect(page.locator('#main-content')).toContainText(
    'Limited-time special offers and curated beauty packages tailored for you.',
  );

  const tabs = page.getByRole('group', { name: 'Jump to deal category' });
  for (const name of [
    'All',
    'Top Deals',
    'Hair Deals',
    'Makeup Deals',
    'Skin Deals',
    'Combo Deals',
    'Referral Deals',
  ]) {
    await expect(tabs.getByRole('button', { name, exact: true })).toBeVisible();
  }

  await expect(page.getByRole('heading', { name: 'Hair Revival Bundle' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Radiance Ritual' })).toBeVisible();
});

test('a pill scrolls to its section — every section stays rendered, not filtered away (ad hoc task 24)', async ({
  page,
}) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const tabs = page.getByRole('group', { name: 'Jump to deal category' });
  await tabs.getByRole('button', { name: 'Skin Deals', exact: true }).click();

  // Every section is still in the DOM — the pill scrolls, it no longer hides the others (the whole
  // point of this task's stacked-sections redesign).
  await expect(page.getByRole('heading', { name: 'Radiance Ritual' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Hair Revival Bundle' })).toBeVisible();

  // The Skin Deals section genuinely scrolled into view — its heading sits near the top of the
  // viewport, just below the sticky header, not off-screen further down the page.
  const skinHeading = page.getByRole('heading', { name: 'Skin Deals' });
  await expect(skinHeading).toBeVisible();
  await expect
    .poll(async () => (await skinHeading.boundingBox())?.y ?? -1, { timeout: 5_000 })
    .toBeLessThan(260);

  // The pill bar highlights the section actually in view.
  await expect(tabs.getByRole('button', { name: 'Skin Deals', exact: true })).toHaveAttribute(
    'aria-pressed',
    'true',
  );
});

test('carousel auto-advances scrollLeft over time and pauses on hover', async ({ page }) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const viewport = page
    .getByRole('group', { name: 'Top Deals carousel' })
    .first();

  const readScroll = () => viewport.evaluate((el) => el.scrollLeft);

  const initial = await readScroll();
  await expect.poll(readScroll, { timeout: 8_000 }).toBeGreaterThan(initial);

  await viewport.hover();
  const paused = await readScroll();
  // No genuine way to assert "stays exactly the same forever" without flaking on timing, so assert
  // it does not keep climbing the way it just did — sampled twice with a real wait between.
  await page.waitForTimeout(2_000);
  const stillPaused = await readScroll();
  expect(stillPaused).toBe(paused);
});

test('manual arrow buttons scroll the carousel deterministically', async ({ page }) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const viewport = page.getByRole('group', { name: 'Top Deals carousel' }).first();
  const readScroll = () => viewport.evaluate((el) => el.scrollLeft);

  await viewport.hover();
  const before = await readScroll();

  const nextButton = page.getByRole('button', { name: 'Scroll Top Deals carousel right' });
  await nextButton.click();

  await expect.poll(readScroll, { timeout: 5_000 }).toBeGreaterThan(before);
});

test('Details opens the interactive modal with real content and Claim/WhatsApp actions', async ({
  page,
}) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const card = page
    .locator('[data-carousel-item]')
    .filter({ hasText: 'Hair Revival Bundle' })
    .first();
  await card.getByRole('button', { name: 'Details' }).click();

  const dialog = page.getByRole('dialog');
  await expect(dialog.getByRole('heading', { name: 'Hair Revival Bundle' })).toBeVisible();
  await expect(dialog).toContainText('High Frequency Hair Voucher');
  await expect(dialog).toContainText('What');
  // The exact ids a deal's real attached services carry can shift with reseeding — only the shape
  // (real service params + the deal's code, ad hoc task 22) is asserted, not a hardcoded href.
  await expect(dialog.getByRole('link', { name: /Claim Offer Now/ })).toHaveAttribute(
    'href',
    /^\/book\?(service=\d+&)+code=HAIRVOUCHER$/,
  );
  await expect(dialog.getByRole('link', { name: /Book on WhatsApp/ })).toHaveAttribute(
    'href',
    'https://wa.me/923059833859',
  );
});

test('card Claim Offer points at a real, direct-booking destination; Add to Cart adds without navigating (ad hoc task 22/23)', async ({
  page,
}) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const card = page
    .locator('[data-carousel-item]')
    .filter({ hasText: 'Hair Revival Bundle' })
    .first();

  // Claim Offer still goes straight to /book with the deal's real services + code — WhatsApp was
  // removed from the card (it remains on the Details modal only) in favor of a real Add to Cart
  // control, no longer "explicitly absent" the way the old card design intended.
  await expect(card.getByRole('link', { name: /Claim Offer/ })).toHaveAttribute(
    'href',
    /^\/book\?(service=\d+&)+code=HAIRVOUCHER$/,
  );
  await expect(card.getByRole('link', { name: 'WhatsApp' })).toHaveCount(0);

  await card.getByRole('button', { name: 'Add to Cart' }).click();
  await expect(page).toHaveURL(/\/deals$/); // no navigation happened
});

test('price breakdown shows strikethrough original, bold deal price and a savings badge', async ({
  page,
}) => {
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const card = page
    .locator('[data-carousel-item]')
    .filter({ hasText: 'Hair Revival Bundle' })
    .first();

  const original = card.getByText('Rs. 14,250');
  await expect(original).toBeVisible();
  const decoration = await original.evaluate((el) => getComputedStyle(el).textDecorationLine);
  expect(decoration).toContain('line-through');

  await expect(card.getByText('Rs. 10,500')).toBeVisible();
  await expect(card.getByText(/% OFF/)).toBeVisible();
});

test('uses theme tokens, not the reference design brown/tan or an ad-hoc color', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const headingFont = await page
    .getByRole('heading', { name: 'Exclusive Deals & Packages' })
    .evaluate((el) => getComputedStyle(el).fontFamily);
  expect(headingFont).toContain('Fraunces');

  const offTheme = await page.evaluate(
    () => document.querySelectorAll('[class*="orange"], [class*="coral"], [class*="brown"], [class*="tan-"]')
      .length,
  );
  expect(offTheme).toBe(0);

  expect(consoleErrors, 'unexpected console errors').toEqual([]);
});

test('renders correctly on a mobile viewport without horizontal page overflow', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: 'Exclusive Deals & Packages' })).toBeVisible();

  const overflows = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  );
  expect(overflows, 'page should not scroll horizontally on mobile').toBe(false);
});

test('renders correctly on a tablet viewport without horizontal page overflow', async ({ page }) => {
  await page.setViewportSize({ width: 820, height: 1180 });
  await page.goto('/deals', { waitUntil: 'domcontentloaded' });

  const overflows = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  );
  expect(overflows, 'page should not scroll horizontally on tablet').toBe(false);
});
