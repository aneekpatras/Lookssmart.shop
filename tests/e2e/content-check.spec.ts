import { expect, test } from '@playwright/test';

/**
 * Real-browser verification for the content sync. The point is to catch the class of bug that
 * server-side assertions cannot: the footer reads a shared Inertia prop, so a prop-name collision
 * or an undefined array would throw only once React actually renders in a browser.
 */
const PAGES = ['/', '/about', '/services', '/contact', '/privacy-policy', '/gallery'];

/**
 * Framer Motion / Lenis apply inline styles at runtime, which this app's `style-src` (nonce-only,
 * no `unsafe-inline`) blocks — reproduced on `/` too, a page this work never touched, so it is a
 * pre-existing issue, not a regression from the content sync. Filtered out here so these specs
 * assert what they are actually about; the underlying finding is logged separately rather than
 * silently swallowed.
 */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

for (const path of PAGES) {
  test(`footer renders real contact details with no page errors on ${path}`, async ({ page }) => {
    const pageErrors: string[] = [];
    const consoleErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(String(error)));
    page.on('console', (message) => {
      if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
        consoleErrors.push(message.text());
      }
    });

    await page.goto(path, { waitUntil: 'domcontentloaded' });

    const footer = page.locator('footer');
    await expect(footer).toContainText('Looks Smart Beauty Salon');
    await expect(footer).toContainText('+92 305 9833859');
    await expect(footer).toContainText('lookssmartbeautysalon@gmail.com');
    await expect(footer).toContainText('Ferozpur Road');
    await expect(footer).toContainText('Monday – Sunday');
    await expect(footer).toContainText('10:00 AM – 9:00 PM');
    await expect(footer).toContainText('© 2026 Looks Smart Beauty Salon. All rights reserved.');

    // Internal links + both social profiles.
    await expect(footer.getByRole('link', { name: 'Privacy Policy' })).toBeVisible();
    await expect(footer.getByRole('link', { name: /on Facebook/ })).toBeVisible();
    await expect(footer.getByRole('link', { name: /on Instagram/ })).toBeVisible();

    // The real signal: an uncaught exception would be exactly what a shadowed shared prop
    // (`site.hours` undefined) produced before the prop was renamed.
    expect(pageErrors, `uncaught page errors on ${path}`).toEqual([]);
    expect(consoleErrors, `unexpected console errors on ${path}`).toEqual([]);
  });
}

test('services page shows real PKR catalog, no dummy USD rows', async ({ page }) => {
  await page.goto('/services', { waitUntil: 'domcontentloaded' });

  // Catalog content assertions live in catalog.spec.ts; this only guards that the page shows a
  // real PKR catalog rather than the original Base44 sample rows.
  const body = page.locator('body');
  await expect(body).toContainText('Rs. ');
  await expect(body).not.toContainText('Relaxing Massage');
  await expect(body).not.toContainText('Classic Haircut');
  await expect(body).not.toContainText('Deep Cleansing Facial');
});

/*
 * The About-page content test that used to live here asserted "Founder & Master Beauty Artist",
 * "What we specialise in", "4 dedicated stations" and "Milestones along the way" — all four of
 * which the About rebuild (ad hoc task 11) deliberately replaced: the title changed to "Founder &
 * Creative Director", the specialties grid became "The Looks Smart Standard", and the ambience and
 * timeline sections are gone. It is superseded by tests/e2e/about-page.spec.ts, which covers the
 * new page in far more depth (section order, the four pillars, the founder card, the settings-driven
 * contact block, theme compliance). Kept as a note rather than silently deleted, because this test
 * failing is exactly how the stale assertions were found.
 */


test('contact page map iframe is pinned on real coordinates and not CSP-blocked', async ({ page }) => {
  // Scoped to frame violations specifically — `frame-src` is what was `'none'` and blocked the map.
  const blocked: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && /frame-src|refused to frame/i.test(message.text())) {
      blocked.push(message.text());
    }
  });

  await page.goto('/contact', { waitUntil: 'domcontentloaded' });

  const map = page.locator('iframe[title="Salon location map"]');
  await expect(map).toHaveAttribute('src', /31\.317056,74\.389306/);
  expect(blocked, 'CSP frame violations').toEqual([]);
});

test('privacy policy has all seven sections and a working back-to-home link', async ({ page }) => {
  await page.goto('/privacy-policy', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: 'Privacy Policy', level: 1 })).toBeVisible();
  await expect(page.locator('body')).toContainText('Effective Date: January 2026');

  for (const heading of [
    '1. Information We Collect',
    '2. How We Use Your Data',
    '3. Data Protection',
    '4. Third-Party Services',
    '5. Cookies',
    '6. Your Rights',
    '7. Contact Us',
  ]) {
    await expect(page.getByRole('heading', { name: heading })).toBeVisible();
  }

  await page.getByRole('link', { name: 'Back to Home' }).first().click();
  // Generous timeout: `php artisan serve` is single-threaded on Windows (no `pcntl_fork()` for
  // PHP_CLI_SERVER_WORKERS — see 02-PROJECT-STATE.md Decision #30), so an Inertia visit can queue
  // behind the asset requests the same page just issued. Not representative of PHP-FPM in prod.
  await expect(page).toHaveURL(/127\.0\.0\.1:8000\/$/, { timeout: 30_000 });
});
