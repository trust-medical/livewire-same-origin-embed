import { expect, test } from '@playwright/test';

test('embeds a Livewire form into the parent DOM without an iframe', async ({ page }) => {
  const events: unknown[] = [];
  await page.exposeFunction('recordBridgeEvent', (event: unknown) => events.push(event));
  await page.addInitScript(() => {
    for (const name of [
      'livewire-bridge:initializing',
      'livewire-bridge:session-ready',
      'livewire-bridge:rendered',
      'livewire-bridge:started',
    ]) {
      document.addEventListener(name, (event) => {
        window.recordBridgeEvent((event as CustomEvent).detail);
      });
    }
  });

  await page.goto('/fixture/non-laravel-page?utm_source=not-forwarded');

  await expect(page.locator('[data-testid="reservation-form"]')).toBeVisible();
  await expect(page.locator('iframe')).toHaveCount(0);
  await expect(page.locator('livewire-bridge [name="name"]')).toBeVisible();
  await expect(page.locator('script[src*="/livewire-"]')).toHaveCount(1);

  expect(await page.evaluate(() => document.querySelector('livewire-bridge form') !== null)).toBe(
    true,
  );
  expect(events.length).toBeGreaterThanOrEqual(4);
});

test('validates and submits through normal Livewire update requests', async ({ page }) => {
  const updateResponses: number[] = [];
  page.on('response', (response) => {
    if (response.url().includes('/livewire-') && response.url().includes('/update')) {
      updateResponses.push(response.status());
    }
  });

  await page.goto('/fixture/non-laravel-page');
  await page.locator('[data-testid="reservation-form"] button[type="submit"]').click();
  await expect(page.locator('[data-testid="name-error"]')).toBeVisible();

  await page.locator('[name="name"]').fill('Taro');
  await page.locator('[data-testid="reservation-form"] button[type="submit"]').click();
  await expect(page.locator('[data-testid="submitted"]')).toContainText('Submitted price-page');
  expect(updateResponses).toContain(200);
});

test('does not forward the current page query string to render', async ({ page }) => {
  const renderRequests: string[] = [];
  page.on('request', (request) => {
    if (request.url().endsWith('/livewire-bridge/render')) {
      renderRequests.push(request.url());
    }
  });

  await page.goto('/fixture/non-laravel-page?utm_campaign=private');
  await expect(page.locator('[data-testid="reservation-form"]')).toBeVisible();
  expect(renderRequests).toHaveLength(1);
  expect(renderRequests[0]).not.toContain('utm_campaign');
});
