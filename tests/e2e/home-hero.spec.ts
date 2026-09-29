import { expect, test } from '@playwright/test';

/**
 * Real-browser checks for the video hero and the filterable "Our Specialized Services" section.
 * A video is exactly the kind of thing that looks fine in markup and is silently dead in a browser
 * — blocked autoplay, an unsupported codec, a CSP `media-src` refusal — so these assert on actual
 * playback state (`readyState`, `currentTime` advancing), not on the element existing.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

test('hero background video is served, muted, looping and actually playing', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const video = page.locator('section video').first();
  await expect(video).toHaveCount(1);

  // The filename genuinely contains spaces; the emitted src must be percent-encoded.
  const src = await video.locator('source').getAttribute('src');
  expect(src).toBe('/images/looks%20smart%20beauty%20Salon%20in%20Lahore.mp4');

  // Poster is present so something is on screen before the first frame decodes.
  await expect(video).toHaveAttribute('poster', /images\.unsplash\.com/);

  // Autoplay policy requires muted; loop/playsinline are what make it a background video.
  expect(await video.evaluate((v: HTMLVideoElement) => v.muted)).toBe(true);
  expect(await video.evaluate((v: HTMLVideoElement) => v.loop)).toBe(true);

  // Enough data buffered to actually render frames (HAVE_CURRENT_DATA or better).
  await expect
    .poll(async () => video.evaluate((v: HTMLVideoElement) => v.readyState), { timeout: 25_000 })
    .toBeGreaterThanOrEqual(2);

  // And genuinely advancing, i.e. not merely loaded-but-paused.
  expect(await video.evaluate((v: HTMLVideoElement) => v.paused)).toBe(false);
  const first = await video.evaluate((v: HTMLVideoElement) => v.currentTime);
  await expect
    .poll(async () => video.evaluate((v: HTMLVideoElement) => v.currentTime), { timeout: 15_000 })
    .toBeGreaterThan(first);

  expect(consoleErrors, 'unexpected console errors').toEqual([]);
});

test('hero keeps brand copy and both CTAs readable over the video', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Looks Smart Beauty Salon');
  // NOTE: not `locator('section').first()` — the Toaster renders an empty aria-live <section>
  // ahead of the page content, so "the first section" is not the hero.
  await expect(page.locator('main')).toContainText(
    'A modern sanctuary for premium skin, hair, and bridal artistry in Lahore',
  );
  await expect(page.getByRole('link', { name: /Book Appointment/ })).toBeVisible();
  await expect(page.getByRole('link', { name: 'View Services' })).toBeVisible();
});

test('specialized services section filters by real category pills', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: 'Our Specialized Services' })).toBeVisible();
  await expect(page.locator('body')).toContainText(
    'Experience premium skin, hair, and beauty treatments in Lahore',
  );

  const group = page.getByRole('group', { name: 'Filter services by category' });
  await expect(group.getByRole('button', { name: 'All' })).toHaveAttribute(
    'aria-pressed',
    'true',
  );

  // Pills reflect the catalog's real categories, not a hardcoded list.
  for (const name of [
    'Hair Styling & Treatments',
    'Skin & Facial Care',
    'Bridal & Party Makeup',
    'Eyelashes, Waxing & Threading',
    'Massage, Spa & Special Services',
  ]) {
    await expect(group.getByRole('button', { name, exact: true })).toBeVisible();
  }

  // Filtering to one category must leave only that category's cards on screen.
  await group.getByRole('button', { name: 'Skin & Facial Care', exact: true }).click();
  await expect(
    group.getByRole('button', { name: 'Skin & Facial Care', exact: true }),
  ).toHaveAttribute('aria-pressed', 'true');

  // A skin service is now on screen and a hair service is not — the filter genuinely applied.
  await expect(page.locator('body')).toContainText('Gold-Infused Hydra Therapy');
  await expect(page.locator('body')).not.toContainText("L'Oréal X-Tenso Smooth");
});

test('service cards expose a working Add to Cart action and Details link (ad hoc task 23)', async ({
  page,
}) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // The card CTA is now "+ Add to Cart" — a real button that merges into the shared cart without
  // navigating (ad hoc task 23), not a direct `/book?service=` link the way it was before.
  const section = page.locator('section', { hasText: 'Our Specialized Services' });
  await section.getByRole('button', { name: 'Add to Cart' }).first().click();
  await expect(page.getByText('added to cart')).toBeVisible();
  await expect(page).toHaveURL('/');

  await expect(section.getByRole('link', { name: 'Details' }).first()).toHaveAttribute(
    'href',
    /\/services\/[a-z0-9-]+/,
  );

  // The footer CTA goes to the full catalog.
  await expect(section.getByRole('link', { name: /View All Services/ })).toHaveAttribute(
    'href',
    '/services',
  );
});

test('cards lay out 4 across on desktop and 1 across on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // Direct-child path on purpose: each card contains its own `grid-cols-2` Book/Details pair, so a
  // bare `.grid` descendant match (especially `.last()`) finds a button row rather than the layout.
  const grid = page
    .locator('section:has-text("Our Specialized Services") > div > div.grid')
    .first();
  const desktopColumns = await grid.evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
  );
  expect(desktopColumns).toBe(4);

  await page.setViewportSize({ width: 390, height: 844 });
  const mobileColumns = await grid.evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
  );
  expect(mobileColumns).toBe(1);
});

test('reduced motion serves a still poster instead of a looping video', async ({ page }) => {
  // Emulated over CDP, NOT via `test.use({ reducedMotion: 'reduce' })`: on @playwright/test 1.62.1
  // in this setup that option does not take effect (probed directly — `matchMedia(...).matches`
  // returned false and the video still mounted). This test previously used it and passed for the
  // WRONG reason: `toHaveCount(0)` matched instantly against the pre-hydration DOM, before the
  // client-only <video> had mounted, so it never actually exercised the reduced-motion branch.
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setEmulatedMedia', {
    features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  expect(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(
    true,
  );

  // The poster must be present first — that proves hydration has run and the hero has rendered, so
  // the video-absence assertion below is meaningful rather than merely early.
  const poster = page.locator('section img[aria-hidden="true"]').first();
  await expect(poster).toHaveAttribute('src', /images\.unsplash\.com/);

  // No video element at all — the download is skipped, not merely paused.
  await expect(page.locator('section video')).toHaveCount(0);

  // And the hero copy is still there and readable.
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Looks Smart Beauty Salon');
});

test('homepage shows the real address and seven-day hours, not placeholders', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const body = page.locator('body');
  await expect(body).toContainText('46-B, Commercial Central Park');
  await expect(body).toContainText('Monday – Sunday');
  await expect(body).toContainText('10:00 AM – 9:00 PM');
  await expect(body).not.toContainText('123 Beauty Lane');
  await expect(body).not.toContainText('9:00 am–7:00 pm');
});
