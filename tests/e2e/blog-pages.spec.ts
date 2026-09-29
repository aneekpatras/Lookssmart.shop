import { expect, test } from '@playwright/test';

/**
 * Real-browser checks for the rebuilt blog listing and article pages. These exist to verify things
 * server-side assertions cannot: that the AVIF cover image genuinely decodes under this app's CSP,
 * that the sanitized article HTML actually renders styled headings/lists/blockquotes rather than
 * just being present in the DOM, and that section order/theme compliance hold visually.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

test.describe('Blog listing (/blog)', () => {
  test('renders hero, featured post, filter tabs and sidebar in order', async ({ page }) => {
    await page.goto('/blog', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Beauty, Skin & Confidence');
    // The page renders a real apostrophe (’, from &rsquo;) rather than a straight one — matched
    // without it to avoid the mismatch rather than relying on the exact glyph.
    await expect(page.locator('main')).toContainText('leading beauty salon and skin clinic');

    // Featured post banner shows the newest article with a working "Read story" link. Matched by
    // shape (a real `/blog/<slug>` link), not a hardcoded slug — the actual newest post is real dev
    // data that legitimately changes as new articles get published, and this assertion only needs to
    // prove the featured banner links somewhere real, not pin WHICH article happens to be newest.
    await expect(page.getByRole('link', { name: /Read story/ })).toHaveAttribute(
      'href',
      /^\/blog\/[a-z0-9-]+$/,
    );

    // Category filter tabs, driven by the real seeded categories.
    const tabs = page.getByRole('group', { name: 'Filter articles by category' });
    for (const name of ['All', 'Hair', 'Skin Care', 'Facial', 'Bridal', 'Laser', 'Tips & Advice']) {
      await expect(tabs.getByRole('button', { name, exact: true })).toBeVisible();
    }

    // Sidebar widgets.
    await expect(page.getByRole('heading', { name: 'Trending this week' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Book a treatment' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Our services' })).toBeVisible();
    await expect(page.getByRole('link', { name: /WhatsApp Us/ })).toHaveAttribute(
      'href',
      'https://wa.me/923059833859',
    );
  });

  test('category tab filters the grid without a full page reload', async ({ page }) => {
    await page.goto('/blog', { waitUntil: 'domcontentloaded' });

    const tabs = page.getByRole('group', { name: 'Filter articles by category' });
    await tabs.getByRole('button', { name: 'Bridal', exact: true }).click();

    // Generous timeout: `php artisan serve` is single-threaded on this machine (no
    // pcntl_fork() for PHP_CLI_SERVER_WORKERS — Decision #30), so the Inertia round trip this
    // click triggers can queue behind other in-flight requests rather than being slow itself.
    await expect(tabs.getByRole('button', { name: 'Bridal', exact: true })).toHaveAttribute(
      'aria-pressed',
      'true',
      { timeout: 20_000 },
    );

    // Scoped to article-card HEADINGS specifically — the "Trending this week" sidebar
    // intentionally lists posts across every category regardless of the active filter, so a
    // page-wide text check would (correctly) still see the Laser article there and fail.
    await expect(
      page.getByRole('heading', { name: 'Bridal Makeup in Lahore', level: 3 }),
    ).toBeVisible();
    await expect(
      page.getByRole('heading', { name: 'Laser Hair Removal in Lahore', level: 3 }),
    ).toHaveCount(0);
  });

  test('quick service links point at the real catalog categories', async ({ page }) => {
    await page.goto('/blog', { waitUntil: 'domcontentloaded' });

    const link = page.getByRole('link', { name: 'Hair Styling & Treatments' });
    await expect(link).toHaveAttribute('href', /\/services\?category=\d+/);
  });

  test('uses theme tokens, not the reference design orange/coral', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
        consoleErrors.push(message.text());
      }
    });

    await page.goto('/blog', { waitUntil: 'domcontentloaded' });

    const headingFont = await page
      .getByRole('heading', { level: 1 })
      .evaluate((el) => getComputedStyle(el).fontFamily);
    expect(headingFont).toContain('Fraunces');

    const offTheme = await page.evaluate(
      () => document.querySelectorAll('[class*="orange"], [class*="coral"]').length,
    );
    expect(offTheme).toBe(0);

    expect(consoleErrors, 'unexpected console errors').toEqual([]);
  });

  test('stacks to a single column on mobile without horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/blog', { waitUntil: 'domcontentloaded' });

    const overflows = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    );
    expect(overflows, 'page should not scroll horizontally on mobile').toBe(false);
  });
});

test.describe('Article detail (/blog/laser-hair-removal-in-lahore-your-questions-answered-honestly)', () => {
  const slug = 'laser-hair-removal-in-lahore-your-questions-answered-honestly';

  test('hero cover image is the real named AVIF asset and it actually decodes', async ({ page }) => {
    await page.goto(`/blog/${slug}`, { waitUntil: 'domcontentloaded' });

    const hero = page.locator('section img').first();
    const src = await hero.getAttribute('src');
    expect(src).toContain('Blog');
    expect(src).toContain('.avif');

    // Genuinely decoded by the browser, not merely present in markup — a CSP-blocked or 404 image
    // still yields an <img> element, so naturalWidth is the only honest check.
    await expect
      .poll(async () => hero.evaluate((img: HTMLImageElement) => img.naturalWidth), {
        timeout: 20_000,
      })
      .toBeGreaterThan(0);
  });

  test('article meta shows category, date, author and working share buttons', async ({ page }) => {
    await page.goto(`/blog/${slug}`, { waitUntil: 'domcontentloaded' });

    await expect(page.locator('main')).toContainText('Laser');
    await expect(page.locator('main')).toContainText('Looks Smart Beauty Team');
    await expect(page.getByRole('link', { name: 'Share on Twitter' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Share on Facebook' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Copy link' })).toBeVisible();
  });

  test('article body renders real styled headings, lists and a CTA blockquote', async ({ page }) => {
    await page.goto(`/blog/${slug}`, { waitUntil: 'domcontentloaded' });

    const body = page.locator('.article-body');
    await expect(body.getByRole('heading', { name: 'Is It Safe?', level: 2 })).toBeVisible();
    await expect(body.locator('ul li').first()).toBeVisible();
    await expect(body.locator('blockquote')).toContainText('Ready to find out if laser is right');

    // Headings must actually be styled with the display font, not just present as plain text.
    const h2Font = await body
      .getByRole('heading', { level: 2 })
      .first()
      .evaluate((el) => getComputedStyle(el).fontFamily);
    expect(h2Font).toContain('Fraunces');
  });

  test('sticky sidebar shows the booking widget, real location and WhatsApp number', async ({
    page,
  }) => {
    await page.goto(`/blog/${slug}`, { waitUntil: 'domcontentloaded' });

    const sidebar = page.locator('aside').last();
    await expect(sidebar.getByRole('heading', { name: 'Book a treatment' })).toBeVisible();
    await expect(sidebar).toContainText('Central Park Housing Scheme');
    await expect(sidebar.getByRole('link', { name: /\+92 305 9833859/ })).toHaveAttribute(
      'href',
      'https://wa.me/923059833859',
    );
  });

  test('bottom conversion banner and related articles render', async ({ page }) => {
    await page.goto(`/blog/${slug}`, { waitUntil: 'domcontentloaded' });

    await expect(
      page.getByRole('heading', { name: 'Ready to Experience This at Looks Smart?' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Book Now' }).last()).toHaveAttribute(
      'href',
      '/book',
    );
    await expect(page.getByRole('link', { name: /WhatsApp Us/ }).last()).toHaveAttribute(
      'href',
      'https://wa.me/923059833859',
    );

    await expect(page.getByRole('heading', { name: 'Related articles' })).toBeVisible();
  });

  test('copy-link button copies the current URL', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto(`/blog/${slug}`, { waitUntil: 'domcontentloaded' });

    await page.getByRole('button', { name: 'Copy link' }).click();
    await expect(page.getByText('Copied!')).toBeVisible();

    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    expect(clipboard).toContain(`/blog/${slug}`);
  });
});
