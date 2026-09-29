import { defineConfig, devices } from '@playwright/test';

/**
 * Phase 14: real-browser E2E, on top of (not replacing) the Pest HTTP-level integration tests in
 * tests/Feature/E2E/. Runs against a real `php artisan serve` + the real local MariaDB dev database
 * (this project's established live-verification pattern throughout every phase) rather than a
 * separate throwaway test database — `webServer` below starts that real server itself so `npx
 * playwright test` works standalone, and reuses an already-running one in local dev instead of
 * fighting over port 8000.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: 'list',
  timeout: 30_000,

  use: {
    baseURL: 'http://127.0.0.1:8000',
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],

  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
});
