import { test, expect } from '@playwright/test';

test('login with amazonpay', async ({ page }) => {
  await page.goto('https://localhost:4430/');
  await page.getByRole('heading', { name: 'おなべ' }).getByRole('link', { name: 'おなべ' }).click();
  await expect(page).toHaveURL('https://localhost:4430/products/detail.php?product_id=2');
  await page.getByRole('link', { name: 'カゴに入れる' }).click();
  await page.getByRole('button', { name: '購入手続きへ' }).click();
  await page.locator('.amazonpay-button-view1').click();
  await page.getByLabel('Eメールアドレス').fill(process.env.SANDBOX_BUYER_ACCOUNT);
  await page.getByLabel('パスワード').fill(process.env.SANDBOX_BUYER_PASSWORD);
  await page.getByRole('button', { name: 'ログイン' }).click();
  await expect(page.locator('.secure-signin')).toHaveText('Amazonアカウントでログインしています');
  await page.locator('input[type="submit"]').first().click();
  await page.getByRole('button', { name: '選択したお届け先に送る' }).click();
  await page.getByText('Amazon Pay').click();
  await page.getByRole('button', { name: '次へ' }).click();
  await page.locator('.amazonpay-button-view1').first().click();
  await expect(page.locator('.maxo-bn-singnin-txt-block')).toHaveText('Amazonアカウントでログインしています');
  await page.waitForTimeout(1000);
  await page.locator('input[type="submit"]').first().click();
  await page.getByRole('button', { name: '次へ' }).click(
    {
      timeout: 300000,
    });
  await page.locator('#next-top').click(
    {
      timeout: 300000,
    });
  await expect(page.locator('h2.title')).toHaveText('ご注文完了');
  await page.getByRole('button', { name: 'Amazon からサインアウト' }).click();
  await page.waitForTimeout(1000);
  await page.getByRole('link', { name: 'トップページへ' }).click();
});
