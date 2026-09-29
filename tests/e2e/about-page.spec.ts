import { expect, test } from '@playwright/test';

/**
 * Verifies the rebuilt About page: the specified 7-section sequence, theme compliance, real
 * settings-driven contact data, and responsive card stacking.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

async function verticalPosition(page: import('@playwright/test').Page, text: string) {
  const locator = page.getByText(text, { exact: false }).first();
  await expect(locator).toBeAttached();

  return locator.evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
}

test('renders all seven sections in the specified order', async ({ page }) => {
  await page.goto('/about', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

  const hero = await verticalPosition(page, 'About Looks Smart Beauty Salon');
  const mission = await verticalPosition(page, 'More than a salon');
  const pillars = await verticalPosition(page, 'The Looks Smart Standard');
  const story = await verticalPosition(page, 'Why we are called Looks Smart');
  const leadership = await verticalPosition(page, 'Meet our Founder');
  const visit = await verticalPosition(page, 'Find Looks Smart Salon');
  const cta = await verticalPosition(page, 'Experience the Looks Smart difference.');

  expect(hero).toBeLessThan(mission);
  expect(mission).toBeLessThan(pillars);
  expect(pillars).toBeLessThan(story);
  expect(story).toBeLessThan(leadership);
  expect(leadership).toBeLessThan(visit);
  expect(visit).toBeLessThan(cta);
});

test('hero shows the badge, intro copy, both CTAs and four quick stats', async ({ page }) => {
  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { level: 1 })).toHaveText(
    'About Looks Smart Beauty Salon',
  );
  await expect(page.locator('main')).toContainText('Our heritage');
  await expect(page.locator('main')).toContainText(
    'Crafting premium beauty, hair, and skin experiences in Lahore',
  );

  for (const [value, label] of [
    ['10+', 'Years of Excellence'],
    ['50+', 'Certified Professionals'],
    ['15K+', 'Happy Clients'],
    ['Lahore', 'Flagship Salon Location'],
  ]) {
    await expect(page.locator('main')).toContainText(value);
    await expect(page.locator('main')).toContainText(label);
  }

  // The booking route is `/book` — the brief said `/booking`, which does not exist in this app.
  await expect(page.getByRole('link', { name: /Book Appointment/ })).toHaveAttribute(
    'href',
    '/book',
  );
  await expect(page.getByRole('link', { name: 'Our Services' })).toHaveAttribute(
    'href',
    '/services',
  );
});

test('the four pillars render as equal-height cards', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  for (const title of ['Care', 'Hygiene', 'Detail', 'Artistry']) {
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
  }

  const grid = page.locator('section:has-text("The Looks Smart Standard") .grid').first();
  const columns = await grid.evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
  );
  expect(columns).toBe(4);

  // Equal height is the point of the brief's "Equal Height" note — grid stretch should make the
  // four cards identical regardless of copy length.
  const heights = await grid.evaluate((el) =>
    Array.from(el.children).map((child) => Math.round(child.getBoundingClientRect().height)),
  );
  expect(new Set(heights).size).toBe(1);
});

test('cards stack to one column on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  const grid = page.locator('section:has-text("The Looks Smart Standard") .grid').first();
  const columns = await grid.evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
  );
  expect(columns).toBe(1);

  const bodyOverflows = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
  );
  expect(bodyOverflows, 'page should not scroll horizontally on mobile').toBe(false);
});

test('founder card shows the real founder, title and quote — and no borrowed portrait', async ({
  page,
}) => {
  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  const section = page.locator('section:has-text("Meet our Founder")').first();
  await expect(section).toContainText('Dua');
  await expect(section).toContainText('Founder & Creative Director');
  await expect(section).toContainText('No two clients should ever leave with the same look.');

  // There is no photograph of the founder in the project; the card must NOT be showing one of the
  // bridal client photos captioned with her name.
  const images = await section.locator('img').count();
  expect(images).toBe(0);
});

test('visit section reads location, phone and hours from real settings', async ({ page }) => {
  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  const section = page.locator('section:has-text("Find Looks Smart Salon")').first();

  await expect(section).toContainText('Central Park Housing Scheme');
  await expect(section).toContainText('+92 305 9833859');
  await expect(section).toContainText('Monday – Sunday');
  await expect(section).toContainText('10:00 AM – 9:00 PM');

  await expect(section.getByRole('link', { name: /Chat on WhatsApp/ })).toHaveAttribute(
    'href',
    'https://wa.me/923059833859',
  );
});

test('bottom CTA banner links to the real booking and services routes', async ({ page }) => {
  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  const banner = page.locator('section:has-text("Experience the Looks Smart difference.")').last();

  await expect(banner).toContainText('Book your appointment today and indulge in luxury care.');
  await expect(banner.getByRole('link', { name: 'Book Now' })).toHaveAttribute('href', '/book');
  await expect(banner.getByRole('link', { name: /View All Services/ })).toHaveAttribute(
    'href',
    '/services',
  );
});

test('uses theme tokens and typography, not ad-hoc colours or fonts', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/about', { waitUntil: 'domcontentloaded' });

  // Display font on headings.
  const headingFont = await page
    .getByRole('heading', { level: 1 })
    .evaluate((el) => getComputedStyle(el).fontFamily);
  expect(headingFont).toContain('Fraunces');

  // The CTA banner is the theme's ink token, not a hardcoded coral/pink from the reference design.
  const banner = page.locator('section:has-text("Experience the Looks Smart difference.") > div');
  expect(await banner.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(
    'rgb(42, 36, 32)',
  );

  // The old page was indigo/purple; nothing on the rebuilt page should be.
  const indigoCount = await page.evaluate(
    () => document.querySelectorAll('[class*="indigo"], [class*="purple"]').length,
  );
  expect(indigoCount).toBe(0);

  expect(consoleErrors, 'unexpected console errors').toEqual([]);
});
