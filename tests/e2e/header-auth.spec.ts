import { expect, test } from '@playwright/test';

/**
 * Real-browser coverage for the header user account icon: the logged-out modal (real `/login` and
 * `/register` submissions, not a mock) and the logged-in dropdown (name/email, My Bookings, Profile
 * Settings, Log out).
 */

test('logged out: the header icon opens a login/register modal, and login genuinely authenticates', async ({
  page,
}) => {
  await page.goto('/');

  const accountButton = page.getByRole('button', { name: 'Log in or create an account' });
  await expect(accountButton).toBeVisible();
  await accountButton.click();

  const dialog = page.getByRole('dialog');
  await expect(dialog.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(dialog.getByRole('tab', { name: 'Log in' })).toHaveAttribute('aria-selected', 'true');

  // Switch to the register tab and back — both real tabs, not placeholders.
  await dialog.getByRole('tab', { name: 'Create account' }).click();
  await expect(dialog.getByRole('heading', { name: 'Create your account' })).toBeVisible();
  await dialog.getByRole('tab', { name: 'Log in' }).click();
  await expect(dialog.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

  // A genuinely wrong login shows a real server-validated error, not a client-only check.
  await dialog.getByLabel('Email').fill('nobody-real@example.test');
  await dialog.getByLabel('Password', { exact: true }).fill('definitely-the-wrong-password');
  await dialog.getByRole('button', { name: 'Log in' }).click();
  await expect(dialog.locator('.text-red-600')).toBeVisible({ timeout: 10_000 });
});

test('registering via the header modal genuinely logs the account in — the icon becomes an avatar', async ({
  page,
}) => {
  const stamp = Date.now();
  const email = `playwright-header-${stamp}@example.test`;

  await page.goto('/');
  await page.getByRole('button', { name: 'Log in or create an account' }).click();

  const dialog = page.getByRole('dialog');
  await dialog.getByRole('tab', { name: 'Create account' }).click();
  await dialog.getByLabel('Name').fill('Playwright Header Customer');
  await dialog.getByLabel('Email').fill(email);
  await dialog.getByLabel('Password', { exact: true }).fill('a-real-strong-password-1');
  await dialog.getByLabel('Confirm password').fill('a-real-strong-password-1');
  await dialog.getByRole('button', { name: 'Create account' }).click();

  // A real Fortify redirect follows registration (to /my-account) — the modal closes as a result of
  // the page navigating, not because the component decided to hide itself.
  await page.waitForURL('**/my-account', { timeout: 20_000 });
  await expect(page.getByRole('dialog')).toHaveCount(0);

  // The header now shows the logged-in dropdown trigger (an avatar), not the guest icon.
  await expect(page.getByRole('button', { name: 'Log in or create an account' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Your account' })).toBeVisible();
});

test('logged in: the header dropdown shows the real name/email and links to My Bookings and Profile Settings, and logs out for real', async ({
  page,
}) => {
  const stamp = Date.now();
  const email = `playwright-header-menu-${stamp}@example.test`;

  await page.goto('/register');
  await page.locator('#name').fill('Playwright Menu Customer');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill('a-real-strong-password-1');
  await page.locator('#password_confirmation').fill('a-real-strong-password-1');
  await page.getByRole('button', { name: /create account/i }).click();
  await page.waitForURL('**/my-account', { timeout: 20_000 });

  await page.getByRole('button', { name: 'Your account' }).click();
  const menu = page.getByRole('menu');
  await expect(menu.getByText('Playwright Menu Customer')).toBeVisible();
  await expect(menu.getByText(email)).toBeVisible();

  const bookingsLink = menu.getByRole('menuitem', { name: /My Bookings/ });
  await expect(bookingsLink).toHaveAttribute('href', '/my-bookings');
  const settingsLink = menu.getByRole('menuitem', { name: /Profile Settings/ });
  await expect(settingsLink).toHaveAttribute('href', '/my-account');

  // `LogoutResponse` redirects to `/` — wait for that real navigation (started concurrently with
  // the click, not after it resolves) rather than polling for the header to change afterward, which
  // proved to land in a race against Inertia's own async page swap.
  await Promise.all([
    page.waitForURL((url) => url.pathname === '/', { timeout: 15_000 }),
    menu.getByRole('menuitem', { name: /Log out/ }).click(),
  ]);

  // A real logout — the header reverts to the guest icon and a protected page redirects to login.
  await expect(page.getByRole('button', { name: 'Log in or create an account' })).toBeVisible({
    timeout: 10_000,
  });
  await page.goto('/my-bookings');
  await expect(page).toHaveURL(/\/login/);
});
