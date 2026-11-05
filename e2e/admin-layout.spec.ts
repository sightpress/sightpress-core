import { expect, test } from '@playwright/test';
import {
  RTL_TEST_LOCALES,
  analyseA11y,
  assertKeyboardJourney,
  assertNoA11yViolations,
  assertPageStructure,
  assertReflows,
  tabTo,
} from '../vendor/sightpress/plugin-core/testkit/playwright/index.ts';

// Every admin screen in M0. New admin routes are added here (CLAUDE.md, Testing expectations).
const ROUTES = [
  { path: '/admin', h1: 'Dashboard' },
  { path: '/admin/fixture-form', h1: 'Fixture form' },
];

for (const route of ROUTES) {
  test.describe(route.path, () => {
    test('passes axe (WCAG 2.2 AA) with no violations', async ({ page }) => {
      await page.goto(route.path);
      await assertNoA11yViolations(page);
    });

    test('has the same structure as every screen and reflows at 320 pixels', async ({ page }) => {
      await page.goto(route.path);
      await assertPageStructure(page, { lang: 'en-GB', dir: 'ltr' });
      await assertReflows(page);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(route.h1);
    });

    test('works with the keyboard alone, with a visible focus indicator at every stop', async ({ page }) => {
      await page.goto(route.path);
      await assertKeyboardJourney(page);
    });

    for (const locale of RTL_TEST_LOCALES) {
      test(`works right to left in ${locale}`, async ({ page }) => {
        await page.goto(`${route.path}?ui_locale=${locale}`);
        await assertPageStructure(page, { lang: locale, dir: 'rtl' });
        await assertReflows(page);
        await assertNoA11yViolations(page);
        // The strings fall back to the source language, and say so.
        await expect(page.locator('body')).toHaveAttribute('lang', 'en-GB');
      });
    }
  });
}

test('the skip link is the first stop and moves focus to the main content', async ({ page }) => {
  await page.goto('/admin');
  await page.keyboard.press('Tab');
  const skip = page.getByRole('link', { name: 'Skip to main content' });
  await expect(skip).toBeFocused();
  await expect(skip).toBeInViewport();
  await page.keyboard.press('Enter');
  await expect(page.locator('main')).toBeFocused();
});

test('a form sent with an error shows the error summary, which takes focus and links to the field', async ({ page }) => {
  await page.goto('/admin/fixture-form');
  await tabTo(page, 'button "Save"');
  await page.keyboard.press('Enter');
  const summary = page.locator('.sp-error-summary');
  await expect(summary).toBeFocused();
  await assertNoA11yViolations(page);
  await assertKeyboardJourney(page);
  await tabTo(page, 'link "Enter a title"');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/#title$/);
  await expect(page.getByRole('textbox', { name: 'Title (required)' })).toHaveAccessibleDescription(/The name shown at the top of the page\. Enter a title/);
});

test('fixing the error and saving announces the result in the polite live region', async ({ page }) => {
  await page.goto('/admin/fixture-form');
  await tabTo(page, 'textbox "Title (required)"');
  await page.keyboard.type('Opening hours');
  await page.keyboard.press('Enter');
  await expect(page.getByRole('status', { name: 'Messages' })).toHaveText('Saved "Opening hours".');
  await expect(page.locator('.sp-error-summary')).toHaveCount(0);
  await assertNoA11yViolations(page);
});

test.describe('in the reader\'s language', () => {
  test.use({ locale: 'de-CH' });

  test('the layout follows the browser language', async ({ page }) => {
    await page.goto('/admin');
    await assertPageStructure(page, { lang: 'de-CH', dir: 'ltr' });
    await assertReflows(page);
    await assertNoA11yViolations(page);
  });
});

test('motion is off when the reader asks for reduced motion', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/admin');
  const duration = await page.locator('a').first().evaluate((el) => getComputedStyle(el).transitionDuration);
  expect(duration.split(',').every((d) => parseFloat(d) <= 0.01)).toBe(true);
});

// The gate must fail on a violation; if this test passes with no violations found, the gate is broken.
test.describe('the gate catches injected violations', () => {
  test('an image with no alt text, a field with no label and low contrast are all reported', async ({ page }) => {
    await page.goto('/admin');
    await page.evaluate(() => {
      const main = document.querySelector('main');
      main?.insertAdjacentHTML('beforeend', '<img src="/missing.png"><input type="text"><p id="faint">Hard to read</p>');
      // Through the CSSOM: the CSP rightly blocks style attributes.
      const faint = document.getElementById('faint');
      if (faint) {
        faint.style.color = '#bbb';
        faint.style.backgroundColor = '#fff';
      }
    });
    const { violations } = await analyseA11y(page);
    const ids = violations.map((v) => v.id);
    expect(ids).toEqual(expect.arrayContaining(['image-alt', 'label', 'color-contrast']));
    await expect(assertNoA11yViolations(page)).rejects.toThrow(/image-alt/);
  });

  test('a second h1 and a missing skip link fail the structure check', async ({ page }) => {
    await page.goto('/admin');
    await page.evaluate(() => {
      document.querySelector('.sp-skip-link')?.remove();
      document.querySelector('main')?.insertAdjacentHTML('beforeend', '<h1>Another title</h1>');
    });
    await expect(assertPageStructure(page)).rejects.toThrow();
  });
});
