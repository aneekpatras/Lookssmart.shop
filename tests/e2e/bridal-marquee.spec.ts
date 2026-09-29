import { expect, test } from '@playwright/test';

/**
 * Real-browser checks for the bridal marquee. The point is to verify things that only exist at
 * runtime: that the CSS animation is actually applied and running (not silently dropped, which is a
 * real risk under this app's no-`unsafe-inline` CSP), that hover genuinely pauses it, that the
 * duplicated track makes the loop seamless, and that the images really decode.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

const track = '.marquee-track';

test('marquee heading, subtitle and cards render', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('body')).toContainText('Looks Smart Brides');
  await expect(page.locator('body')).toContainText(
    'Moments of beauty and elegance from our real brides',
  );

  // Portrait cards, per the brief's aspect-[3/4].
  const card = page.locator(`${track} li`).first();
  const box = await card.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.height).toBeGreaterThan(box!.width);
});

test('animation is genuinely applied and running, not dropped by CSP', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const state = await page.locator(track).evaluate((el) => {
    const style = getComputedStyle(el);
    return {
      name: style.animationName,
      duration: style.animationDuration,
      iteration: style.animationIterationCount,
      timing: style.animationTimingFunction,
      playState: style.animationPlayState,
    };
  });

  expect(state.name).toBe('marquee-scroll');
  expect(state.iteration).toBe('infinite');
  expect(state.timing).toBe('linear');
  expect(state.playState).toBe('running');
  // Per-image cadence: 4 seeded bridal photos x ~3s.
  expect(state.duration).toBe('12s');

  // The transform must actually be advancing, i.e. the animation is really compositing.
  const readMatrix = () =>
    page.locator(track).evaluate((el) => getComputedStyle(el).transform);
  const first = await readMatrix();
  await expect.poll(readMatrix, { timeout: 10_000 }).not.toBe(first);

  expect(consoleErrors, 'unexpected console errors').toEqual([]);
});

test('hovering an image pauses the scroll', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // Hover the stationary viewport, not a card: the cards are actively translating, so a
  // locator.hover() on one can land in the gap between cards after the box is computed. Playwright's
  // locator.hover() also handles the scroll-into-view and viewport-relative coordinates correctly —
  // a manual page.mouse.move() with boundingBox() values does NOT, because boundingBox() is in page
  // coordinates while mouse.move() expects viewport coordinates (which is what broke this first).
  const viewport = page.locator('.marquee-viewport');
  await viewport.hover();

  await expect
    .poll(async () => page.locator(track).evaluate((el) => getComputedStyle(el).animationPlayState), {
      timeout: 5_000,
    })
    .toBe('paused');

  // Moving away resumes it, so a hover cannot permanently stall the slider.
  await page.mouse.move(0, 0);
  await expect
    .poll(async () => page.locator(track).evaluate((el) => getComputedStyle(el).animationPlayState), {
      timeout: 5_000,
    })
    .toBe('running');
});

test('the track is duplicated so the loop has no gap', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // `locator.count()` does NOT auto-wait, so it must not be called before hydration has rendered
  // the track — doing so returned 0 for the first count and 4 for the second, which looked like a
  // duplication bug but was purely a timing artefact of the test.
  await expect(page.locator(`${track} li`).first()).toBeVisible();

  const total = await page.locator(`${track} li`).count();
  const announced = await page.locator(`${track} li:not([aria-hidden="true"])`).count();

  // Exactly two passes, and only one is exposed to assistive tech.
  expect(total).toBe(announced * 2);
  expect(announced).toBeGreaterThan(0);
});

test('bridal images actually decode', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const image = page.locator(`${track} img`).first();
  await expect
    .poll(async () => image.evaluate((img: HTMLImageElement) => img.naturalWidth), {
      timeout: 20_000,
    })
    .toBeGreaterThan(0);
});

test('viewport supports touch drag via native horizontal scrolling', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const overflow = await page
    .locator('.marquee-viewport')
    .evaluate((el) => getComputedStyle(el).overflowX);

  expect(overflow).toBe('auto');
});

test('renders correctly on a mobile viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await expect(page.locator(track)).toHaveCount(1);
  await expect(page.locator(`${track} li`).first()).toBeVisible();

  // The section must not make the PAGE scroll sideways — the marquee scrolls, the body does not.
  const bodyOverflows = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  );
  expect(bodyOverflows, 'page should not scroll horizontally on mobile').toBe(false);
});

test('reduced motion cancels the animation instead of parking the track off-screen', async ({
  page,
}) => {
  // Emulated over CDP rather than Playwright's `reducedMotion` context option: on
  // @playwright/test 1.62.1 in this setup that option does NOT take effect — probed directly and
  // `matchMedia('(prefers-reduced-motion: reduce)').matches` came back false, so any test relying
  // on it silently exercises the ordinary path. CDP genuinely flips the media feature.
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setEmulatedMedia', {
    features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  expect(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(
    true,
  );

  const state = await page.locator(track).evaluate((el) => {
    const style = getComputedStyle(el);
    return { name: style.animationName, transform: style.transform };
  });

  // The global reduced-motion backstop would otherwise run the animation to completion in 0.01ms
  // and leave the track parked at translateX(-50%), looking broken.
  expect(state.name).toBe('none');
  expect(['none', 'matrix(1, 0, 0, 1, 0, 0)']).toContain(state.transform);

  // Cards are still visible and still hand-scrollable.
  await expect(page.locator(`${track} li`).first()).toBeVisible();
});
