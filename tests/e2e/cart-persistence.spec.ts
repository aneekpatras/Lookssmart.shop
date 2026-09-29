import { expect, test } from '@playwright/test';

/**
 * Real-browser coverage for the global "Add to Cart" flow (ad hoc task 22): a deal or service added
 * from Home/Deals lands on /book with the real bundled services already pre-selected AND the promo
 * code applied, and adding from a SECOND page afterwards unions into the same cart rather than
 * resetting it (sessionStorage-backed persistence, since /book is a fresh page load each visit).
 *
 * Resilient to WHICH real seeded deal is used — picks the first homepage offer card that actually has
 * a promo-code tag, rather than hardcoding a deal name.
 */
test('claiming a homepage deal pre-selects its real services and applies its promo code on /book', async ({
  page,
}) => {
  await page.goto('/');

  const offerSection = page.locator('section', { hasText: 'Current offers' });
  const firstOffer = offerSection.locator('.rounded-lg.border').first();
  await expect(firstOffer).toBeVisible();

  await firstOffer.getByRole('link', { name: 'Claim Offer' }).click();
  await page.waitForURL(/\/book\?/);

  // At least one service card came pre-checked ("Added") on the services step.
  await expect(page.getByRole('button', { name: /: Added$/ }).first()).toBeVisible();

  // The promo code field only renders on the checkout step, but the code itself was already
  // captured from the URL on load (`?code=...`) — Continue confirms it carried through.
  await page.locator('aside', { hasText: 'Your selection' }).getByRole('button', { name: 'Continue' }).click();
  const promo = page.locator('aside').getByLabel('Promo Code');
  await expect(promo).toBeVisible({ timeout: 10_000 });
  await expect(promo).not.toHaveValue('');
});

test('adding a service from Home (no navigation), then claiming a deal from /deals, unions both into the same /book cart', async ({
  page,
}, testInfo) => {
  // 4 sequential full page navigations (/, /book, /deals, then the deal-claim redirect to /book)
  // against the local dev server, which is genuinely single-threaded (Decision #30) and can take
  // ~5s per request — comfortably enough to exhaust the default 30s test budget on its own.
  testInfo.setTimeout(60_000);
  await page.goto('/');

  // Home's services section CTA is a real "Add to Cart" — clicking it must NOT navigate (ad hoc
  // task 23), only toast and merge into sessionStorage.
  const servicesSection = page.locator('section', { hasText: 'Our Specialized Services' });
  await servicesSection.getByRole('button', { name: 'Add to Cart' }).first().click();
  await expect(page.getByText('added to cart')).toBeVisible();
  await expect(page).toHaveURL('/');

  await page.goto('/book');
  const firstAddedName = await page.getByRole('button', { name: /: Added$/ }).first().getAttribute('aria-label');
  expect(firstAddedName).toBeTruthy();
  const firstCount = await page.getByRole('button', { name: /: Added$/ }).count();

  await page.goto('/deals');
  const dealCard = page.locator('[data-carousel-item]').first();
  await dealCard.getByRole('link', { name: 'Claim Offer' }).click();
  await page.waitForURL(/\/book\?/);

  // The service Home added earlier is STILL "Added" — sessionStorage carried it across this second,
  // separate page load — and the deal's own services are unioned in on top, not replacing it.
  await expect(page.getByRole('button', { name: firstAddedName! })).toHaveCount(1);
  const secondCount = await page.getByRole('button', { name: /: Added$/ }).count();
  expect(secondCount).toBeGreaterThan(firstCount);
});
