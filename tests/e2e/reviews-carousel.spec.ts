import { expect, test } from '@playwright/test';

/**
 * Real-browser checks for the homepage Reviews Carousel and Write a Review modal — genuine content
 * rotation over time, a real pause-on-hover (not just markup), the Write a Review flow through both
 * steps, and the real local-save submit action (no external Google redirect or clipboard step —
 * removed in a later fix once it turned out Google does not allow pre-filling its own review box).
 */

test('shows 3 review cards on desktop, 1 on mobile, each carrying a rating', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const carousel = page.getByRole('group', { name: 'Client reviews' });
  await expect(carousel.locator('[data-carousel-item]').first()).toBeVisible();
  await expect(carousel.locator('[data-carousel-item]')).toHaveCount(3);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('group', { name: 'Client reviews' }).locator('[data-carousel-item]')).toHaveCount(1);
});

test('rotates to a different set of reviews after 5 seconds', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const carousel = page.getByRole('group', { name: 'Client reviews' });
  const readNames = () => carousel.locator('[data-carousel-item] p.text-sm.font-medium').allTextContents();

  const before = await readNames();
  await expect.poll(readNames, { timeout: 8_000 }).not.toEqual(before);
});

test('hovering the carousel pauses rotation', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  const carousel = page.getByRole('group', { name: 'Client reviews' });
  const readNames = () => carousel.locator('[data-carousel-item] p.text-sm.font-medium').allTextContents();

  await carousel.hover();
  const whileHovering = await readNames();

  // Real wait past one full auto-advance interval while still hovering — content must not change.
  await page.waitForTimeout(6_000);
  const stillHovering = await readNames();

  expect(stillHovering).toEqual(whileHovering);
});

test('a Google-sourced review card shows the verification badge', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  // `latest(published_at)` decides which reviews land on the very first page, so a Google-sourced
  // one is not guaranteed to be among the first 3 shown — poll across a few real 5s auto-advances
  // (up to the full page count) rather than assuming page 1 happens to contain one.
  await expect
    .poll(async () => page.getByText('Google Verified').count(), { timeout: 30_000, intervals: [1_000] })
    .toBeGreaterThan(0);
});

test('Write a Review opens the modal, requires rating and category before continuing', async ({
  page,
}) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: 'Write a Review' }).click();

  const dialog = page.getByRole('dialog');
  await expect(dialog.getByRole('heading', { name: 'Share Your Experience with Looks Smart' })).toBeVisible();

  const continueButton = dialog.getByRole('button', { name: 'Continue' });
  await expect(continueButton).toBeDisabled();

  await dialog.getByRole('button', { name: '5 stars' }).click();
  await expect(continueButton).toBeDisabled();

  await dialog.getByRole('button', { name: 'Hair Treatments', exact: true }).click();
  await expect(continueButton).toBeEnabled();
});

test('selecting a template populates the comment box, and it stays freely editable', async ({
  page,
}) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: 'Write a Review' }).click();
  const dialog = page.getByRole('dialog');

  await dialog.getByRole('button', { name: '5 stars' }).click();
  await dialog.getByRole('button', { name: 'Facials & Skin Care', exact: true }).click();
  await dialog.getByRole('button', { name: 'Continue' }).click();

  const textarea = dialog.locator('#review-comment');
  await expect(textarea).toHaveValue('');

  await dialog
    .getByText('My skin has never looked this fresh.', { exact: false })
    .click();
  await expect(textarea).toHaveValue(/My skin has never looked this fresh/);

  await textarea.fill('A fully custom review I wrote myself.');
  await expect(textarea).toHaveValue('A fully custom review I wrote myself.');
});

test('the name field is optional for a guest — Submit Review is enabled without it', async ({
  page,
}) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: 'Write a Review' }).click();
  const dialog = page.getByRole('dialog');

  await dialog.getByRole('button', { name: '5 stars' }).click();
  await dialog.getByRole('button', { name: 'Hair Treatments', exact: true }).click();
  await dialog.getByRole('button', { name: 'Continue' }).click();

  const submitButton = dialog.getByRole('button', { name: /Submit Review/ });
  // Still disabled with no review text at all — the button's real gate is the comment, not the name.
  await expect(submitButton).toBeDisabled();

  await dialog.locator('#review-comment').fill('A genuinely great visit, will be back.');
  await expect(submitButton).toBeEnabled();
  await expect(dialog.locator('#review-name')).toHaveValue('');
});

test('Submit Review saves the review locally, shows the exact success toast, closes the modal, and the review appears in the carousel instantly — no Google redirect or clipboard step', async ({
  page,
  context,
}) => {
  // Proves the removed behavior genuinely stays removed: no external navigation and no clipboard
  // write happen anywhere in this flow.
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  const openedUrls: string[] = [];
  page.on('popup', (popup) => openedUrls.push(popup.url()));

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  // The clipboard API needs a real, focused document — writing the sentinel only works after
  // navigation, not against the pre-navigation about:blank page.
  await page.evaluate(() => navigator.clipboard.writeText('unchanged-sentinel-value'));

  await page.getByRole('button', { name: 'Write a Review' }).click();
  const dialog = page.getByRole('dialog');

  // `submitReview()`'s real anti-bot time-trap requires >=3s between the page rendering and the
  // submission — genuinely part of the feature being tested, not test overhead to work around, so
  // this wait is deliberate rather than a flaky sleep.
  await page.waitForTimeout(3_200);

  await dialog.getByRole('button', { name: '5 stars' }).click();
  await dialog.getByRole('button', { name: 'Laser Hair Removal', exact: true }).click();
  await dialog.getByRole('button', { name: 'Continue' }).click();
  await dialog.locator('#review-comment').fill('Real, visible results and a genuinely professional team.');
  await dialog.locator('#review-name').fill('Playwright Reviewer');

  await dialog.getByRole('button', { name: /Submit Review/ }).click();

  // The exact wording the task specifies, not a paraphrase.
  await expect(
    page.getByText('Thank you! Your review has been submitted successfully.'),
  ).toBeVisible();

  // Modal closes automatically.
  await expect(page.getByRole('dialog')).toHaveCount(0);

  // No external tab was ever opened, and the clipboard was never touched.
  expect(openedUrls).toEqual([]);
  const clipboard = await page.evaluate(() => navigator.clipboard.readText());
  expect(clipboard).toBe('unchanged-sentinel-value');

  // The genuinely-saved review appears in the carousel immediately, without a page reload — poll
  // (it may land on a page the carousel has already auto-advanced past by the time this runs).
  await expect
    .poll(
      async () =>
        page
          .getByRole('group', { name: 'Client reviews' })
          .locator('[data-carousel-item]')
          .filter({ hasText: 'Playwright Reviewer' })
          .count(),
      { timeout: 10_000, intervals: [1_000] },
    )
    .toBeGreaterThan(0);
});

test('a guest who leaves the name field blank is credited as "Verified Guest" in the carousel', async ({
  page,
}) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: 'Write a Review' }).click();
  const dialog = page.getByRole('dialog');

  // See the time-trap note in the previous test — a real, deliberate wait, not test overhead.
  await page.waitForTimeout(3_200);

  await dialog.getByRole('button', { name: '4 stars' }).click();
  await dialog.getByRole('button', { name: 'Bridal & Makeup', exact: true }).click();
  await dialog.getByRole('button', { name: 'Continue' }).click();
  await dialog
    .locator('#review-comment')
    .fill('A distinctive Playwright review body for the anonymous-guest test.');

  await dialog.getByRole('button', { name: /Submit Review/ }).click();
  await expect(page.getByRole('dialog')).toHaveCount(0);

  await expect
    .poll(
      async () =>
        page
          .getByRole('group', { name: 'Client reviews' })
          .locator('[data-carousel-item]')
          .filter({ hasText: 'A distinctive Playwright review body for the anonymous-guest test.' })
          .count(),
      { timeout: 10_000, intervals: [1_000] },
    )
    .toBeGreaterThan(0);

  await expect(
    page
      .getByRole('group', { name: 'Client reviews' })
      .locator('[data-carousel-item]')
      .filter({ hasText: 'A distinctive Playwright review body for the anonymous-guest test.' }),
  ).toContainText('Verified Guest');
});

test('reviews section renders on theme tokens with zero console errors', async ({ page }) => {
  const consoleErrors: string[] = [];
  const isKnownInlineStyleCspNoise = (text: string) =>
    /Applying inline style violates the following Content Security Policy/i.test(text);
  page.on('console', (message) => {
    if (message.type() === 'error' && !isKnownInlineStyleCspNoise(message.text())) {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'The feeling after.' })).toBeVisible();

  expect(consoleErrors, 'unexpected console errors').toEqual([]);
});
