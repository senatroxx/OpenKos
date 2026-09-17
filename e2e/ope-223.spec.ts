import { readFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import type { Page } from 'playwright/test';
import { expect, test } from 'playwright/test';

type Fixture = {
    property: string;
    unit: string;
    target_unit: string;
    unit_type: number;
    tenant: string;
};

const fixturePath = path.join(
    os.tmpdir(),
    `openkos-${process.env.OPENKOS_E2E_NAME ?? 'ope-223'}-playwright.json`,
);
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8')) as Fixture;

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill('budi@openkos.com');
    await page.locator('#password').fill('password');
    await page.locator('[data-test="login-button"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
}

test('manages inherited pricing and exposes effective rates in assignment', async ({
    page,
}) => {
    await login(page);

    await page.goto(`/properties/${fixture.property}/unit-types`);
    const unitTypeRow = page.locator('tr').filter({ hasText: 'OPE-223 Studio' });
    await unitTypeRow.getByRole('button', { name: 'Unit Type actions' }).click();
    await page.getByRole('menuitem', { name: 'Manage pricing' }).click();
    await expect(page).toHaveURL(/\/unit-types\/\d+\/rates$/);
    await expect(page.getByText('These rates are inherited')).toBeVisible();
    await expect(page.locator('input[value^="1000000"]')).toBeVisible();
    await expect(page.locator('input[value^="2700000"]')).toBeVisible();

    await page.goto(`/properties/${fixture.property}/units/${fixture.unit}`);
    await page.getByRole('button', { name: 'Unit actions' }).click();
    await page
        .getByRole('menuitem', { name: /Assign Tenant|Tetapkan penyewa/i })
        .click();
    const dialog = page.getByRole('dialog').last();
    await dialog
        .locator('label')
        .filter({ hasText: fixture.tenant })
        .locator('input[type="checkbox"]')
        .check();
    await expect(dialog.getByText('3 months')).toBeVisible();
    await dialog.getByText('3 months').click();
    await dialog
        .getByRole('button', { name: /Assign Tenant|Tetapkan penyewa/i })
        .click();
    await expect(
        page.getByText(/Tenant assigned to unit|Penyewa ditetapkan ke unit/i),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Unit actions' }).click();
    await page.getByRole('menuitem', { name: /Move Unit|Pindahkan unit/i }).click();
    const moveSheet = page.getByRole('dialog').last();
    await moveSheet.getByRole('combobox').click();
    await page.getByText('OPE-223 Unit 2').last().click();
    await moveSheet.getByRole('button', { name: /Move Tenant|Pindahkan penyewa/i }).click();
    await expect(
        page.getByText(/Tenant moved to new unit|Penyewa dipindahkan ke unit baru/i),
    ).toBeVisible();
});

test('shows effective public starting prices and listing readiness', async ({
    page,
}) => {
    await login(page);

    await page.goto(`/properties/${fixture.property}/listing`);
    await expect(page.getByText('1 Unit Type')).toBeVisible();
    await expect(page.getByText('Listed').last()).toBeVisible();

    await page.goto(`/listings/${fixture.property}`);
    await expect(page.getByText('OPE-223 Studio')).toBeVisible();
    await expect(page.getByText(/1[.,]250[.,]000/)).toBeVisible();
});
