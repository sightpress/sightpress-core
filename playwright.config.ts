import { defineConfig, devices } from '@playwright/test';

// The admin layout in a real browser, with axe-core (WCAG 2.2 AA), page
// structure, reflow at 320 pixels, a keyboard journey, light and dark, and
// the he-IL and ar-SA RTL test locales. PHP's built-in server runs the
// fixture app (tests/Fixtures/App/public/index.php); run composer install first.
const port = Number(process.env.PORT ?? 8123);

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: {
    baseURL: `http://127.0.0.1:${port}`,
    // The source locale; browsers send their own language otherwise, and the admin follows it.
    locale: 'en-GB',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop-light', use: { ...devices['Desktop Chrome'], colorScheme: 'light' } },
    { name: 'desktop-dark', use: { ...devices['Desktop Chrome'], colorScheme: 'dark' } },
    { name: 'mobile-320', use: { ...devices['Pixel 5'], viewport: { width: 320, height: 640 } } },
  ],
  webServer: {
    command: `php -S 127.0.0.1:${port} tests/Fixtures/App/public/index.php`,
    url: `http://127.0.0.1:${port}/admin`,
    reuseExistingServer: !process.env.CI,
    stdout: 'ignore',
    stderr: 'pipe',
  },
});
