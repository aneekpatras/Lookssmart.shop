import { expect, test } from '@playwright/test';

/**
 * Verifies the homepage's required section SEQUENCE and the new WhatsApp support card.
 *
 * Order is asserted by reading each landmark's real vertical position in the document rather than
 * by counting sections, so the test still means something if sections are added or a conditional
 * one (offers, testimonials, the gallery marquee) is absent.
 */

/** Framer Motion / Lenis inline-style CSP violations — pre-existing, tracked as §10 #39. */
const isKnownInlineStyleCspNoise = (text: string) =>
  /Applying inline style violates the following Content Security Policy/i.test(text);

async function verticalPosition(page: import('@playwright/test').Page, text: string) {
  const locator = page.getByText(text, { exact: false }).first();
  await expect(locator).toBeAttached();

  return locator.evaluate((el) => {
    const rect = el.getBoundingClientRect();
    return rect.top + window.scrollY;
  });
}

test('sections appear in the required order', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

  const hero = await verticalPosition(page, 'A modern sanctuary for premium skin');
  const services = await verticalPosition(page, 'Our Specialized Services');
  const deals = await verticalPosition(page, 'Current offers');
  const legacy = await verticalPosition(page, 'Why Trust Looks Smart?');
  const gallery = await verticalPosition(page, 'Moments of beauty and elegance');
  const reviews = await verticalPosition(page, 'The feeling after.');
  const whatsapp = await verticalPosition(page, 'Talk to us on WhatsApp');

  const footer = await page
    .locator('footer')
    .evaluate((el) => el.getBoundingClientRect().top + window.scrollY);

  // 1 Hero -> 2 Services -> 3 Deals -> 4 Legacy -> 5 Gallery -> 6 Reviews -> 7 WhatsApp -> 8 Footer
  expect(hero).toBeLessThan(services);
  expect(services).toBeLessThan(deals);
  expect(deals).toBeLessThan(legacy);
  expect(legacy).toBeLessThan(gallery);
  expect(gallery).toBeLessThan(reviews);
  expect(reviews).toBeLessThan(whatsapp);
  expect(whatsapp).toBeLessThan(footer);
});

test('Why Trust section shows all six stats on a dark band', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const section = page.locator('section', { hasText: 'Why Trust Looks Smart?' }).first();
  await expect(section).toBeVisible();

  for (const [value, label] of [
    ['12+', 'Years of Excellence'],
    ['50+', 'Expert Stylists'],
    ['15K+', 'Happy Clients'],
    ['500+', 'Transformations'],
    ['100%', 'Sterilized Tools'],
    ['4.9★', 'Google Rating'],
  ]) {
    await expect(section).toContainText(value);
    await expect(section).toContainText(label);
  }

  // The band is genuinely the dark, high-contrast one — reading the resolved theme token, not a
  // hardcoded hex, so this keeps passing if the palette is retuned.
  const background = await section.evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(background).toBe('rgb(42, 36, 32)'); // --color-ink
});

test('gallery carousel is inset from the screen edges', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const viewport = page.locator('.marquee-viewport');
  await expect(viewport).toBeAttached();

  const box = await viewport.boundingBox();
  expect(box).not.toBeNull();
  // lg:px-16 = 64px each side, so the strip must not reach either edge.
  expect(box!.x).toBeGreaterThanOrEqual(60);
  expect(box!.x + box!.width).toBeLessThanOrEqual(1440 - 60);

  await expect(page.getByRole('link', { name: /View Full Gallery/ })).toHaveAttribute(
    'href',
    '/gallery',
  );
});

test('WhatsApp card renders a real scannable QR and a working chat link', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const section = page.locator('section', { hasText: 'Talk to us on WhatsApp' }).first();

  await expect(section).toContainText('Instant Support');
  await expect(section).toContainText('Book appointments, ask about services, request a quote');
  await expect(section).toContainText('+92 305 9833859');
  await expect(section).toContainText('Scan with your camera');

  // The QR is a server-generated SVG data URI, and must actually decode in the browser — a
  // malformed data: URI still yields an <img> element, so naturalWidth is the honest check.
  const qr = section.locator('img').first();
  await expect(qr).toHaveAttribute('src', /^data:image\/svg\+xml;base64,/);
  await expect
    .poll(async () => qr.evaluate((img: HTMLImageElement) => img.naturalWidth), { timeout: 15_000 })
    .toBeGreaterThan(0);

  const button = section.getByRole('link', { name: /Chat on WhatsApp/ });
  await expect(button).toHaveAttribute('href', 'https://wa.me/923059833859');
  await expect(button).toHaveAttribute('target', '_blank');
  await expect(button).toHaveAttribute('rel', /noopener/);

  // WhatsApp brand green is the one colour the brief allows to be hardcoded.
  expect(await button.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(
    'rgb(37, 211, 102)',
  );

  expect(consoleErrors, 'unexpected console errors').toEqual([]);
});

test('new sections use the theme typography, not ad-hoc fonts', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  for (const heading of ['Why Trust Looks Smart?', 'Talk to us on WhatsApp']) {
    const font = await page
      .getByRole('heading', { name: heading })
      .evaluate((el) => getComputedStyle(el).fontFamily);

    // --font-display
    expect(font).toContain('Fraunces');
  }
});
