import { expect, test } from '@playwright/test';

/**
 * Real-browser customer booking journey against the 2-step wizard's 3-column service grid, "+ Add"
 * button cards, sticky side-panel CTA, and compact grouped time-slot picker. On top of (not a
 * replacement for) `tests/Feature/BookingWizardOverhaulTest.php`'s HTTP-level coverage of `notes`/
 * `subject` folding and admin-sync behavior.
 *
 * Runs against the real local dev database — no fixture seeding here, deliberately: this project's
 * own established pattern throughout every phase is live-verification against the real dev
 * server/database, not a throwaway test fixture set.
 *
 * Resilient to WHICH services/dates are actually bookable rather than hardcoding names — picks the
 * first available option at each step and retries across a few upcoming dates, since business hours
 * can legitimately leave a specific day fully booked or closed.
 */
test('a guest can browse a 3-column grid, add a service by button, see a live running total, hold a slot, and complete a real booking', async ({
  page,
}, testInfo) => {
  // A full multi-step booking journey against the local dev server, which is genuinely
  // single-threaded (Decision #30) and can take several real seconds per request — this was
  // measured passing at 29.2s against the default 30s budget, with no margin for normal variance.
  testInfo.setTimeout(60_000);
  await page.goto('/book');

  // Category filter pills, driven by the real ServiceCategory list, narrow the grid instantly with
  // no page reload. Resilient to WHICH category actually has active services — a real category with
  // zero services (found live: "Nails" in this catalog) is a legitimate, valid state the pills must
  // still render correctly, not something to special-case around; this test just needs ONE category
  // that has services to continue through, and tries each pill in turn rather than assuming index 1
  // is never empty.
  const categoryFilter = page.getByRole('group', { name: 'Filter services by category' });
  await expect(categoryFilter).toBeVisible();
  const categoryPillCount = await categoryFilter.getByRole('button').count();
  // Each service card carries its own "+ Add" button (the card itself is no longer the clickable
  // target) — clicking it toggles the card's state to "Added". The button's accessible name is
  // "{service name}: Add"/"Added" (not the bare word) — a real accessibility fix made during this
  // task, since a screen-reader user tabbing through many otherwise-identical "Add" buttons on the
  // 3-column grid had no way to tell which service each one belonged to. The `$` anchor matters:
  // "...: Added" would otherwise substring-match a "...: Add" query too.
  const addButton = page.getByRole('button', { name: /: Add$/ }).first();
  for (let index = 1; index < categoryPillCount; index += 1) {
    await categoryFilter.getByRole('button').nth(index).click();
    if (await addButton.isVisible().catch(() => false)) break;
  }
  await expect(addButton).toBeVisible();
  await addButton.click();
  await expect(page.getByRole('button', { name: /: Added$/ }).first()).toBeVisible();

  // The persistent side summary reflects the selection live, with no server round trip.
  const summary = page.locator('aside', { hasText: 'Your selection' });
  await expect(summary).toContainText('Total Amount');

  // The primary CTA lives inside the sticky side panel itself, not at the bottom of the page.
  const continueButton = summary.getByRole('button', { name: 'Continue' });
  await expect(continueButton).toBeVisible();
  await continueButton.click();

  const dateInput = page.locator('#booking-date');
  await expect(dateInput).toBeVisible({ timeout: 10_000 });

  // Slots load automatically on entering the step — no "Find times" click needed. Each date change
  // triggers a real fetch; wait for it to genuinely settle (either a slot button or the "no times"
  // copy appears) rather than a fixed sleep, since the request time is real network/DB latency.
  //
  // `disabled: false` matters here, not just style — ad hoc task 30's capacity grid still RENDERS a
  // full slot (dimmed, "· Not Available", a genuine HTML `disabled` button) rather than omitting it,
  // and this long session's own accumulated real e2e-test bookings have genuinely filled several
  // early hours on "today" for some services — an unfiltered `.first()` could resolve to one of those
  // disabled buttons and then hang forever waiting for a hold that a disabled button can never start.
  const slotButton = page.getByRole('button', { name: /\d{1,2}:\d{2}\s?(AM|PM)/i, disabled: false }).first();
  const noTimesMessage = page.getByText('No times found for this date.');

  async function waitForSlotsToSettle() {
    await Promise.race([
      slotButton.waitFor({ state: 'visible', timeout: 10_000 }),
      noTimesMessage.waitFor({ state: 'visible', timeout: 10_000 }),
    ]).catch(() => {});
  }

  await waitForSlotsToSettle();
  let found = await slotButton.isVisible().catch(() => false);
  for (let offset = 1; offset < 7 && !found; offset += 1) {
    const target = new Date();
    target.setDate(target.getDate() + offset);
    await dateInput.fill(target.toISOString().slice(0, 10));
    await waitForSlotsToSettle();
    found = await slotButton.isVisible().catch(() => false);
  }
  expect(found, 'expected at least one bookable slot within the next 7 days').toBe(true);

  // The compact picker groups slots under a Morning/Afternoon/Evening heading — whichever period
  // the found slot falls into must show a real period label above it.
  const periodHeading = page.getByText(/^(Morning|Afternoon|Evening)$/).first();
  await expect(periodHeading).toBeVisible();

  await slotButton.click();

  // A real hold now exists server-side (Redis SET NX); the countdown proves it. Ad hoc task 30's
  // capacity-grid refactor made `hold()` do genuinely more work per request than before (a capacity
  // check, then resolving and trying real staff candidates, each its own DB/Redis round trip) — on
  // the known-slow single-threaded local dev server (Decision #30), especially inside a long batch
  // run, that can comfortably exceed the default 5s assertion timeout on its own with no code bug
  // involved (confirmed via isolated reruns completing well within this file's own 60s test budget).
  await expect(page.getByText(/Your time is held for/)).toBeVisible({ timeout: 15_000 });

  // Customer details, the date picker, and the slot grid are all on this same screen — no separate
  // "Your details" step.
  const stamp = Date.now();
  await page.locator('#booking-name').fill('Playwright E2E Guest');
  await page.locator('#booking-email').fill(`playwright-e2e-${stamp}@example.test`);
  await page.locator('#booking-phone').fill('03001234567');
  await page.locator('#booking-subject').fill('First visit — quick question');
  await page.locator('#booking-notes').fill('Please use fragrance-free products.');

  // The "Promo Code" field (relabeled from "Promo code (optional)") lives in the side panel too.
  await expect(summary.getByLabel('Promo Code')).toBeVisible();

  // One button does what used to be "Review" + "Confirm booking" — no separate quote-review screen —
  // and it's the same sticky panel, no scrolling required to reach it.
  await summary.getByRole('button', { name: 'Confirm Booking' }).click();

  await expect(page.getByRole('heading', { name: /all booked/i })).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText('Your confirmation code is')).toBeVisible();
});

test('the service grid lays out 3 across on desktop and 1 across on mobile', async ({ page }) => {
  await page.goto('/book');

  const grid = page.getByTestId('service-grid').first();
  await expect(grid.locator(':scope > *').first()).toBeVisible();

  const columns = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
  expect(columns).toBe(3);

  await page.setViewportSize({ width: 390, height: 844 });
  const mobileColumns = await grid.evaluate(
    (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
  );
  expect(mobileColumns).toBe(1);
});

test('the booking progress indicator shows exactly 2 steps, not 5', async ({ page }) => {
  await page.goto('/book');

  const progress = page.getByRole('group', { name: 'Booking progress' });
  await expect(progress.getByText('Select services')).toBeVisible();
  await expect(progress.getByText('Date, time & details')).toBeVisible();
  await expect(progress.getByText('Staff')).toHaveCount(0);
  await expect(progress.getByText('Confirm')).toHaveCount(0);
});

test('there is no staff-selection step anywhere in the wizard', async ({ page }) => {
  await page.goto('/book');

  await expect(page.getByRole('button', { name: 'Any available' })).toHaveCount(0);

  await page.getByRole('button', { name: /: Add$/ }).first().click();
  await page
    .locator('aside', { hasText: 'Your selection' })
    .getByRole('button', { name: 'Continue' })
    .click();

  await expect(page.getByRole('button', { name: 'Any available' })).toHaveCount(0);
});

test('the side summary and Add/Added button state update live as services are added and removed', async ({
  page,
}) => {
  await page.goto('/book');

  const summary = page.locator('aside', { hasText: 'Your selection' });
  await expect(summary).toContainText('Select a service to begin.');

  // The `$` anchor throughout matters: "...: Added" would otherwise substring-match a "...: Add"
  // query too.
  const addButtons = page.getByRole('button', { name: /: Add$/ });
  await addButtons.nth(0).click();
  await expect(page.getByRole('button', { name: /: Added$/ })).toHaveCount(1);
  await expect(summary).not.toContainText('Select a service to begin.');
  const afterOne = await summary.textContent();

  await addButtons.nth(0).click(); // the next remaining "Add" button, now the 2nd card
  await expect(page.getByRole('button', { name: /: Added$/ })).toHaveCount(2);
  const afterTwo = await summary.textContent();
  expect(afterTwo).not.toEqual(afterOne);

  // Clicking an "Added" button removes it again — the card reverts and the summary shrinks back.
  await page.getByRole('button', { name: /: Added$/ }).first().click();
  await expect(page.getByRole('button', { name: /: Added$/ })).toHaveCount(1);
});

test('adding a service to cart from /services, opening the header cart drawer, and continuing to booking preselects it (ad hoc task 23/24)', async ({
  page,
}) => {
  // The /services listing's card CTA is a real "+ Add to Cart" (no direct link to /book anymore,
  // ad hoc task 23) — the header cart icon now opens a real slide-over drawer (ad hoc task 24)
  // rather than navigating straight to /book; "Continue to Booking" inside it is the real path from
  // there, with whatever accumulated in the cart already pre-checked.
  await page.goto('/services');
  await page.getByRole('button', { name: 'Add to Cart' }).first().click();
  await expect(page.getByText('added to cart')).toBeVisible();

  const cartButton = page.getByRole('button', { name: /View cart, 1 item selected/ });
  await expect(cartButton).toBeVisible();
  await cartButton.click();

  const drawer = page.getByRole('dialog');
  await expect(drawer.getByRole('heading', { name: 'Your Cart' })).toBeVisible();
  await drawer.getByRole('link', { name: 'Continue to Booking' }).click();
  await page.waitForURL(/\/book/);

  await expect(page.getByRole('button', { name: /: Added$/ }).first()).toBeVisible();

  const summary = page.locator('aside', { hasText: 'Your selection' });
  await expect(summary).not.toContainText('Select a service to begin.');
});
