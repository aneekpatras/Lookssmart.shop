import { expect, test } from '@playwright/test';

/**
 * Real-browser checks for the catalog overhaul. These exist to verify the things server-side
 * assertions cannot: that the Unsplash stock images actually LOAD under the app's CSP, that the
 * card is genuinely the shorter height, and that `?service=` really lands the customer on the
 * wizard with their service already ticked.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

test('service cards render loaded stock images at the reduced height', async ({ page }) => {
  const cspBlocks: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      cspBlocks.push(message.text());
    }
  });

  await page.goto('/services', { waitUntil: 'domcontentloaded' });

  const firstImage = page.locator('main img').first();
  await expect(firstImage).toHaveAttribute('src', /images\.unsplash\.com/);

  // Genuinely decoded by the browser, not merely present in the markup — a CSP-blocked or 404
  // image still yields an <img> element, so naturalWidth is the only honest check.
  await expect
    .poll(async () => firstImage.evaluate((img: HTMLImageElement) => img.naturalWidth), {
      timeout: 20_000,
    })
    .toBeGreaterThan(0);

  // The image well should be the ~190px band, not a tall 4:3 placeholder.
  const wellHeight = await firstImage.evaluate(
    (img) => (img.parentElement as HTMLElement).getBoundingClientRect().height,
  );
  expect(wellHeight).toBeGreaterThan(150);
  expect(wellHeight).toBeLessThan(215);

  expect(cspBlocks, 'unexpected console errors').toEqual([]);
});

test('prices render as discounted PKR amounts', async ({ page }) => {
  await page.goto('/services', { waitUntil: 'domcontentloaded' });

  const body = page.locator('body');
  await expect(body).toContainText('Rs. 12,000'); // L'Oréal X-Tenso Smooth
  await expect(body).toContainText("L'Oréal X-Tenso Smooth");
});

test('Details opens the dynamic detail view with procedure and aftercare', async ({ page }) => {
  await page.goto('/services', { waitUntil: 'domcontentloaded' });

  await page.getByRole('link', { name: 'Details' }).first().click();
  await expect(page).toHaveURL(/\/services\/[a-z0-9-]+$/, { timeout: 30_000 });

  await expect(page.getByRole('heading', { name: 'Overview' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'What happens in the appointment' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Aftercare' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Is this right for me?' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Benefits' })).toBeVisible();
  await expect(page.getByRole('link', { name: /Book this service/ }).first()).toBeVisible();
});

test('a chemical smoothing service shows its 72-hour no-wash aftercare', async ({ page }) => {
  await page.goto('/services/loreal-x-tenso-smooth', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('body')).toContainText('72 hours');
  await expect(page.locator('body')).toContainText('sulphate-free');
  // Duration shown in human form, not "180 min".
  await expect(page.locator('body')).toContainText('3 hours');
});

test('a facial shows sun-protection aftercare instead', async ({ page }) => {
  await page.goto('/services/gold-infused-hydra-therapy', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('body')).toContainText('SPF 30');
  await expect(page.locator('body')).not.toContainText('72 hours');
});

test('Book now preselects the correct service in the wizard', async ({ page }) => {
  await page.goto('/services/permanent-rebonding', { waitUntil: 'domcontentloaded' });

  await page.getByRole('link', { name: /Book this service/ }).first().click();
  await expect(page).toHaveURL(/\/book\?service=\d+$/, { timeout: 30_000 });

  // The wizard's step-1 "+ Add"/"Added" button for this service must come up already toggled to
  // "Added" (its accessible name carries the real service name — "{name}: Add"/"Added" — precisely
  // so a screen-reader user tabbing through many otherwise-identical Add buttons on the 3-column
  // grid gets real context), and the summary sidebar must list it.
  const tile = page.getByRole('button', { name: /Permanent Rebonding: Added/ });
  await expect(tile).toHaveAttribute('aria-pressed', 'true', { timeout: 20_000 });
  await expect(page.locator('aside')).toContainText('Permanent Rebonding');
  await expect(page.locator('aside')).not.toContainText('Select a service to begin');
});

test('an unknown ?service= id is ignored rather than poisoning the selection', async ({ page }) => {
  await page.goto('/book?service=999999', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('aside')).toContainText('Select a service to begin', {
    timeout: 20_000,
  });
});
