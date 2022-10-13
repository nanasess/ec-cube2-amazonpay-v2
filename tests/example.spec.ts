import { test, expect } from '@playwright/test';

test('homepage has Playwright in title and get started link linking to the intro page', async ({ page }) => {
  await page.goto('https://localhost:4430/');
  await expect(page).toHaveTitle(/EC-CUBE/);
  await page.getByRole('heading', { name: 'おなべ' }).getByRole('link', { name: 'おなべ' }).click();
  await expect(page).toHaveURL('https://localhost:4430/products/detail.php?product_id=2');
  await page.getByRole('link', { name: 'カゴに入れる' }).click();
  await page.locator('.amazonpay-button-view1').click();
  await page.getByLabel('Eメールアドレス').fill('jp-amazonpay-tester@amazon.co.jp');
  await page.getByLabel('パスワード').fill('jp-amazonpay-tester@amazon.co.jp');
  await page.getByRole('button', { name: 'ログイン' }).click();
  await page.locator('input[type="submit"]').click();
  await page.getByRole('button', { name: '次へ' }).click(
    {
      timeout: 100000,
    }
  );
  await page.locator('input[name="next-top"]').click();
});
