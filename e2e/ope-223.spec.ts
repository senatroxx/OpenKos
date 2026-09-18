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

test('navigates the Unit Type workspace tabs', async ({ page }) => {
    await login(page);

    await page.goto(`/properties/${fixture.property}/unit-types`);
    await page.locator('tr').filter({ hasText: 'OPE-223 Studio' }).click();
    await expect(page).toHaveURL(/\/unit-types\/\d+$/);
    await expect(
        page.getByText('Property: OPE-223 Pricing Property'),
    ).toBeVisible();
    await expect(page.getByText('Pricing').last()).toBeVisible();

    await page.getByRole('link', { name: /^(Units|Unit)$/i }).click();
    await expect(page).toHaveURL(/\/unit-types\/\d+\/units$/);
    await expect(page.getByText('OPE-223 Unit 1')).toBeVisible();

    await page.getByRole('link', { name: /^(Pricing|Harga)$/i }).click();
    await expect(page).toHaveURL(/\/unit-types\/\d+\/rates$/);
    await expect(
        page.locator('tbody tr').filter({ hasText: 'IDR' }).first(),
    ).toBeVisible();

    await page.getByRole('link', { name: /^Listing$/i }).click();
    await expect(page).toHaveURL(/\/unit-types\/\d+\/listing$/);
    await expect(page.getByText('Public listing')).toBeVisible();
});

test('manages inherited pricing and exposes effective rates in assignment', async ({
    page,
}) => {
    await login(page);

    await page.goto(`/properties/${fixture.property}/unit-types`);
    const unitTypeRow = page
        .locator('tr')
        .filter({ hasText: 'OPE-223 Studio' });
    await unitTypeRow
        .getByRole('button', { name: 'Unit Type actions' })
        .click();
    await page.getByRole('menuitem', { name: 'Manage pricing' }).click();
    await expect(page).toHaveURL(/\/unit-types\/\d+\/rates$/);
    await expect(
        page.locator('tbody tr').filter({ hasText: 'IDR' }).first(),
    ).toBeVisible();
    await expect(page.getByText(/1[.,]000[.,]000/)).toBeVisible();
    await expect(page.getByText(/2[.,]700[.,]000/)).toBeVisible();

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
    await page
        .getByRole('menuitem', { name: /Move Unit|Pindahkan unit/i })
        .click();
    const moveSheet = page.getByRole('dialog').last();
    await moveSheet.getByRole('combobox').click();
    await page.getByText('OPE-223 Unit 2').last().click();
    await moveSheet
        .getByRole('button', { name: /Move Tenant|Pindahkan penyewa/i })
        .click();
    await expect(
        page.getByText(
            /Tenant moved to new unit|Penyewa dipindahkan ke unit baru/i,
        ),
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
    await expect(page.getByText(/(?:Rp|IDR)/)).toBeVisible();
});

test('manages Unit Type pricing CRUD', async ({ page }) => {
    await login(page);

    await page.goto(
        `/properties/${fixture.property}/unit-types/${fixture.unit_type}/rates`,
    );
    await page.getByRole('button', { name: /Add Rate|Tambah tarif/i }).click();

    const dialog = page.getByRole('dialog');
    await dialog.locator('#new-rate-amount').fill('333000');
    await dialog.locator('input[type="number"]').nth(1).fill('4');
    await dialog
        .getByRole('button', { name: /Add Rate|Tambah tarif/i })
        .click();

    const rateRow = page.locator('tbody tr').filter({ hasText: '333' }).first();
    await expect(rateRow).toContainText(/333[.,]000/);
    await expect(rateRow).toContainText(/Active|Aktif/);

    await rateRow.getByRole('button').click();
    await page
        .getByRole('menuitem', { name: /Edit amount|Edit jumlah/i })
        .click();
    const editRow = page
        .locator('tbody tr')
        .filter({ has: page.locator('input[type="number"]') })
        .last();
    await editRow.locator('input[type="number"]').fill('444000');
    await editRow.getByRole('button', { name: /Save|Simpan/i }).click();
    await expect(
        page.locator('tbody tr').filter({ hasText: '444' }).first(),
    ).toContainText(/444[.,]000/);

    const updatedRow = page
        .locator('tbody tr')
        .filter({ hasText: '444' })
        .first();
    await updatedRow.getByRole('button').click();
    await page
        .getByRole('menuitem', { name: /Deactivate|Nonaktifkan/i })
        .click();

    await page.getByRole('combobox').nth(1).click();
    await page
        .getByRole('option', { name: /All statuses|Semua status/i })
        .click();
    const inactiveRow = page
        .locator('tbody tr')
        .filter({ hasText: '444' })
        .first();
    await expect(inactiveRow).toContainText(/Inactive|Nonaktif|Tidak aktif/);

    await inactiveRow.getByRole('button').click();
    await page.getByRole('menuitem', { name: /Reactivate|Aktifkan/i }).click();
    await expect(updatedRow).toContainText(/Active|Aktif/);
});
