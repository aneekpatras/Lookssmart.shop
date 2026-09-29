import { expect, test } from '@playwright/test';

/**
 * Real-browser regression coverage for ad hoc task 35, per an explicit spec that reverses part of
 * task 34: service duration/closing-time must no longer disable a slot — only the salon-wide capacity
 * cap can — and slots step every 30 minutes (10:00 AM, 10:30 AM, ..., 9:00 PM inclusive) instead of
 * every hour. "Your Selection" also gained a Total Service Time indicator above Total Amount.
 */

test('time slots step every 30 minutes from 10:00 AM to 9:00 PM, and a very long selection never disables any of them', async ({
  page,
}) => {
  await page.goto('/book');
  // Add 3 long, real services (well over the length of a single business day combined) — under task
  // 34's now-reversed rule this would have left almost every slot disabled; task 35 requires all of
  // them stay selectable. Named explicitly (not by shifting `nth()` position, which moves as each
  // click flips its own button from "Add" to "Added" and drops out of the `/: Add$/` match set).
  for (const name of ["L'Oréal X-Tenso Smooth: Add", 'Signature Extenso Straightening: Add', 'Permanent Rebonding: Add']) {
    await page.getByRole('button', { name }).click();
  }

  await page.locator('aside', { hasText: 'Your selection' }).getByRole('button', { name: 'Continue' }).click();
  const dateInput = page.locator('#booking-date');
  await expect(dateInput).toBeVisible({ timeout: 10_000 });

  const allSlots = page.getByRole('button', { name: /\d{1,2}:\d{2}\s?(AM|PM)/i });
  const enabledSlots = page.getByRole('button', { name: /\d{1,2}:\d{2}\s?(AM|PM)/i, disabled: false });
  const noTimes = page.getByText('No times found for this date.');

  // Resilient to which real date has room, matching this suite's established pattern.
  let total = 0;
  for (let offset = 1; offset < 10 && total === 0; offset += 1) {
    const target = new Date();
    target.setDate(target.getDate() + offset);
    await dateInput.fill(target.toISOString().slice(0, 10));
    await Promise.race([
      allSlots.first().waitFor({ state: 'visible', timeout: 10_000 }),
      noTimes.waitFor({ state: 'visible', timeout: 10_000 }),
    ]).catch(() => {});
    total = await allSlots.count();
  }

  expect(total, 'expected a date with real slots').toBeGreaterThan(0);
  const labels = await allSlots.allTextContents();
  expect(labels, 'the last slot mark should be 9:00 PM itself').toContain('9:00 PM');
  expect(labels, 'a 30-min mark like 10:30 should exist alongside on-the-hour marks').toContain('10:30 AM');

  // The whole point of task 35: nothing here should be disabled by duration/closing time — every
  // returned slot should be genuinely selectable (capacity is the only thing that could disable one,
  // and this fresh test data has no competing bookings on the chosen date/slot).
  expect(await enabledSlots.count()).toBe(total);
});

test('"Your Selection" shows a Total Service Time above Total Amount, summing every selected service\'s real duration', async ({
  page,
}) => {
  await page.goto('/book');
  // 2 real, named services with known durations: 180 + 180 = 360 min = "6 Hours" exactly.
  for (const name of ["L'Oréal X-Tenso Smooth: Add", 'Signature Extenso Straightening: Add']) {
    await page.getByRole('button', { name }).click();
  }

  const aside = page.locator('aside', { hasText: 'Your selection' });
  const durationRow = aside.locator('text=Total Service Time').locator('..');
  await expect(durationRow).toBeVisible();
  await expect(durationRow).toContainText('6 Hours');

  // Placed directly above Total Amount, not somewhere disconnected from it.
  const totalAmountRow = aside.locator('text=Total Amount').locator('..');
  const durationBox = await durationRow.boundingBox();
  const totalBox = await totalAmountRow.boundingBox();
  expect(durationBox!.y).toBeLessThan(totalBox!.y);
});
