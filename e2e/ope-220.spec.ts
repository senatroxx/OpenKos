import { expect, test as base } from 'playwright/test';
import type { Locator, Page } from 'playwright/test';
import { readFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

type Fixture = {
    owner: { email: string; password: string };
    portal: { email: string; password: string };
    wholeTarget: { slug: string; tenant: string };
    wholeOccupied: { slug: string; lease: number; tenant: string };
    unitOccupied: {
        slug: string;
        unit: string;
        tenant: string;
        wholeTenant: string;
    };
    renewUnitConflict: { lease: number };
    renewWholeConflict: { lease: number };
    renewUnitUnrelated: { lease: number };
    renewNormal: { lease: number };
};

const fixturePath = path.join(os.tmpdir(), 'openkos-ope-220-playwright.json');
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8')) as Fixture;

const test = base.extend<{ pageErrors: string[] }>({
    pageErrors: async ({ page }, use) => {
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await use(errors);

        expect(errors, `browser page errors: ${errors.join('; ')}`).toEqual([]);
    },
});

async function login(
    page: Page,
    credentials: { email: string; password: string },
): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(credentials.email);
    await page.locator('#password').fill(credentials.password);
    await page.locator('[data-test="login-button"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function chooseWholePropertyTenant(
    dialog: Locator,
    tenantName: string,
): Promise<void> {
    const tenantLabel = dialog
        .locator('label')
        .filter({ hasText: tenantName })
        .first();
    await tenantLabel.getByRole('checkbox').click();
}

function assignTenantButton(dialog: Locator): Locator {
    return dialog.getByRole('button', {
        name: /Assign Tenant|Assign Tenants|Tetapkan penyewa/i,
    });
}

async function openWholePropertyLeaseForm(
    page: Page,
    propertySlug: string,
): Promise<Locator> {
    await page.goto(`/properties/${propertySlug}/leases`);
    await page
        .getByRole('button', { name: 'New whole-property lease' })
        .click();

    const dialog = page.getByRole('dialog').last();
    await expect(dialog).toContainText(
        'Lease the full property without assigning a unit.',
    );

    return dialog;
}

async function openRenewal(
    page: Page,
    leaseReference: string,
): Promise<Locator> {
    await page.goto('/leases');
    const row = page.locator('tr').filter({ hasText: leaseReference }).first();
    await expect(row).toBeVisible();
    await row.getByRole('button').last().click();
    await page
        .getByRole('menuitem', { name: /Renew|Perbarui/i, exact: true })
        .click();

    const dialog = page.getByRole('dialog').last();
    await expect(dialog).toContainText(/Renew Lease|Perbarui sewa/i);

    return dialog;
}

async function submitRenewal(page: Page, dialog: Locator): Promise<void> {
    await dialog.getByLabel(/Extension|Perpanjangan/i).fill('1');
    await dialog
        .getByRole('button', { name: /Renew Lease|Perbarui sewa/i })
        .click();
}

test('creates a whole-property lease and exposes truthful public availability', async ({
    page,
}) => {
    await login(page, fixture.owner);

    const dialog = await openWholePropertyLeaseForm(
        page,
        fixture.wholeTarget.slug,
    );
    await chooseWholePropertyTenant(dialog, fixture.wholeTarget.tenant);
    await dialog.getByRole('button', { name: 'Create lease' }).click();

    await expect(page.getByText('Whole-property lease created.')).toBeVisible();
    const leaseRow = page.locator('tr').filter({ hasText: 'Entire property' });
    await expect(leaseRow).toBeVisible();

    await page.goto(`/properties/${fixture.wholeTarget.slug}/units`);
    await expect(
        page.locator('tr').filter({ hasText: 'Whole Target Unit' }),
    ).toContainText(/Available|Tersedia/);

    await page.goto(`/listings/${fixture.wholeTarget.slug}`);
    await expect(
        page.getByText(/Unavailable|Tidak tersedia/, { exact: true }),
    ).toBeVisible();
});

test('blocks whole-vs-unit creation conflicts without changing unit status', async ({
    page,
}) => {
    await login(page, fixture.owner);

    await page.goto(`/properties/${fixture.wholeOccupied.slug}/units`);
    const unitRow = page
        .locator('tr')
        .filter({ hasText: 'Whole Occupied Unit' })
        .first();
    await unitRow.click();
    const detailDialog = page.getByRole('dialog').last();
    await assignTenantButton(detailDialog).click();

    const unitLeaseDialog = page.getByRole('dialog').last();
    await unitLeaseDialog
        .locator('label')
        .filter({ hasText: fixture.wholeOccupied.tenant })
        .locator('input[type="checkbox"]')
        .check();
    const unitConflictResponse = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            response.status() === 422 &&
            response.url().includes('/leases'),
    );
    await assignTenantButton(unitLeaseDialog).click();
    expect(await (await unitConflictResponse).text()).toContain(
        'This property is already leased as a whole property.',
    );

    await page.goto(`/properties/${fixture.wholeOccupied.slug}/units`);
    await expect(
        page.locator('tr').filter({ hasText: 'Whole Occupied Unit' }),
    ).toContainText(/Available|Tersedia/);

    const wholeDialog = await openWholePropertyLeaseForm(
        page,
        fixture.unitOccupied.slug,
    );
    await chooseWholePropertyTenant(
        wholeDialog,
        fixture.unitOccupied.wholeTenant,
    );
    const wholeConflictResponse = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            response.status() === 422 &&
            response.url().endsWith('/leases'),
    );
    await wholeDialog.getByRole('button', { name: 'Create lease' }).click();
    expect(await (await wholeConflictResponse).text()).toContain(
        'This property already has an active lease.',
    );
});

test('enforces target conflicts during renewal and allows valid renewals', async ({
    page,
}) => {
    await login(page, fixture.owner);

    const unitConflictDialog = await openRenewal(page, 'OPE220-RENEW-UNIT');
    await submitRenewal(page, unitConflictDialog);
    await expect(
        page.getByText('Unit already has an active lease.'),
    ).toBeVisible();

    const wholeConflictDialog = await openRenewal(page, 'OPE220-RENEW-WHOLE');
    await submitRenewal(page, wholeConflictDialog);
    await expect(
        page.getByText('Property already has another active lease.'),
    ).toBeVisible();

    const unrelatedDialog = await openRenewal(page, 'OPE220-RENEW-UNRELATED');
    await submitRenewal(page, unrelatedDialog);
    await expect(
        page.getByText('Lease renewed. New lease created.'),
    ).toBeVisible();

    const normalDialog = await openRenewal(page, 'OPE220-RENEW-NORMAL');
    await submitRenewal(page, normalDialog);
    await expect(
        page.getByText('Lease renewed. New lease created.'),
    ).toBeVisible();
});

test('keeps whole-property leases usable in the tenant portal, billing, and maintenance', async ({
    page,
}) => {
    await login(page, fixture.portal);

    await page.goto('/portal/dashboard');
    await expect(
        page.getByText('OPE-220 Whole Occupied', { exact: true }),
    ).toBeVisible();

    await page.goto('/portal/lease');
    await expect(
        page.getByText('OPE-220 Whole Occupied', { exact: true }),
    ).toBeVisible();

    await page.goto('/portal/maintenance-tickets');
    await page
        .getByRole('button', { name: /Report Issue|Laporkan masalah/i })
        .click();
    const maintenanceDialog = page.getByRole('dialog').last();
    await maintenanceDialog.getByRole('combobox').click();
    await page.getByRole('option', { name: /Property|Properti/i }).click();
    await maintenanceDialog
        .getByPlaceholder(/e\.g\. Leaking faucet|contoh: Keran bocor/i)
        .fill('Whole-property maintenance issue');
    await maintenanceDialog
        .getByPlaceholder(
            /Describe the issue in detail|Jelaskan masalah secara rinci/i,
        )
        .fill('The property-level water supply needs attention.');
    await maintenanceDialog
        .getByRole('button', { name: /Submit|Kirim/i })
        .click();
    await expect(
        page.getByText('Whole-property maintenance issue'),
    ).toBeVisible();

    await page.goto('/portal/billing');
    await page
        .getByRole('link', { name: /View invoice|Lihat detail/i })
        .first()
        .click();
    await expect(page.getByText('OPE220-WHOLE-ACTIVE')).toBeVisible();
    await expect(page.getByText('Entire property')).toBeVisible();
    await expect(page.getByText(/2[.,]500[.,]000/).first()).toBeVisible();
});

test('keeps direct property lineage visible to staff views and exports', async ({
    page,
}) => {
    await login(page, fixture.owner);

    await page.goto(`/properties/${fixture.wholeOccupied.slug}/units`);
    await page.locator('tr').filter({ hasText: 'Whole Occupied Unit' }).click();
    const detailDialog = page.getByRole('dialog').last();
    await assignTenantButton(detailDialog).click();
    await expect(
        page.getByRole('dialog').last().getByText('OPE-220 Portal Tenant'),
    ).toBeVisible();

    await page.goto('/tenants');
    await page
        .getByPlaceholder(/Search by name|Cari berdasarkan nama/i)
        .fill('OPE-220 Portal Tenant');
    await expect(page.getByText('OPE-220 Portal Tenant')).toBeVisible();

    const exportResponse = await page.request.get(
        '/data-transfer/tenants/export',
    );
    expect(exportResponse.ok()).toBeTruthy();
    expect(await exportResponse.text()).toContain('OPE-220 Portal Tenant');
});
