<?php

use App\Http\Controllers\Dashboard\FinancialController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\RentController;
use App\Http\Controllers\DataTransferController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseInvoiceController;
use App\Http\Controllers\LeaseRentScheduleController;
use App\Http\Controllers\MaintenanceTicketController;
use App\Http\Controllers\PaymentAttemptController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyAmenityController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyDocumentsController;
use App\Http\Controllers\PropertyLeasesController;
use App\Http\Controllers\PropertyMediaController;
use App\Http\Controllers\PropertyUnitTypeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SignedPaymentController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantDocumentController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantPortal\DashboardController as TenantPortalDashboardController;
use App\Http\Controllers\TenantPortal\LeaseController as TenantPortalLeaseController;
use App\Http\Controllers\TenantPortal\NotificationController as TenantPortalNotificationController;
use App\Http\Controllers\TenantPortal\PaymentController as TenantPortalPaymentController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitTypeMediaController;
use App\Http\Controllers\UnitUtilityController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::prefix('invitations')->name('users.invitations.')->middleware('guest')->group(function () {
    Route::get('{token}', [UserController::class, 'acceptInvitation'])->name('accept');
    Route::post('accept', [UserController::class, 'completeInvitation'])->name('complete');
});

Route::prefix('tenants/invitations')->name('tenants.invitations.')->middleware('guest')->group(function () {
    Route::get('{token}', [TenantInvitationController::class, 'acceptInvitation'])->name('accept');
    Route::post('accept', [TenantInvitationController::class, 'completeInvitation'])->name('complete');
});

Route::prefix('pay/invoices/{token}')
    ->where(['token' => '[A-Za-z0-9_-]+'])
    ->middleware('throttle:30,1')
    ->group(function () {
        Route::get('/', [SignedPaymentController::class, 'show'])->name('payments.signed.show');
        Route::post('/', [SignedPaymentController::class, 'pay'])->name('payments.signed.pay');
    });

Route::middleware(['auth', 'verified'])->prefix('portal')->name('portal.')->group(function () {
    Route::redirect('/', '/portal/dashboard');
    Route::get('dashboard', TenantPortalDashboardController::class)->name('dashboard');
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [TenantPortalNotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [TenantPortalNotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [TenantPortalNotificationController::class, 'markAllAsRead'])->name('read-all');
    });
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [TenantPortalPaymentController::class, 'index'])->name('index');
        Route::post('/', [TenantPortalPaymentController::class, 'store'])->name('store');
        Route::get('history/invoices', [TenantPortalPaymentController::class, 'invoiceHistory'])->name('history.invoices');
        Route::get('history/payments', [TenantPortalPaymentController::class, 'paymentHistory'])->name('history.payments');
        Route::get('invoices/{invoice}', [TenantPortalPaymentController::class, 'invoice'])->name('invoices.show');
        Route::post('invoices/{invoice}/pay', [TenantPortalPaymentController::class, 'pay'])->name('invoices.pay');
        Route::get('invoices/{invoice}/print', [TenantPortalPaymentController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/download', [TenantPortalPaymentController::class, 'download'])->name('invoices.download');
    });

    Route::prefix('lease')->name('lease.')->group(function () {
        Route::get('/', [TenantPortalLeaseController::class, 'index'])->name('index');
        Route::prefix('{lease}')->whereNumber('lease')->group(function () {
            Route::get('/', [TenantPortalLeaseController::class, 'show'])->name('show');
        });
    });

    Route::prefix('maintenance-tickets')->name('maintenance-tickets.')->group(function () {
        Route::get('/', [App\Http\Controllers\TenantPortal\MaintenanceTicketController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\TenantPortal\MaintenanceTicketController::class, 'store'])->name('store');
        Route::get('{ticket}', [App\Http\Controllers\TenantPortal\MaintenanceTicketController::class, 'show'])->name('show');
    });
});

Route::middleware(['auth', 'verified', 'permission:dashboard.view'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', OverviewController::class)->name('dashboard');
        Route::get('rent', RentController::class)->name('dashboard.rent');
        Route::get('financial', FinancialController::class)
            ->name('dashboard.financial')
            ->middleware('permission:financials.view');
    });

    Route::prefix('data-transfer')->name('data-transfer.')->group(function () {
        Route::get('/', [DataTransferController::class, 'index'])->name('index');
        Route::post('preview', [DataTransferController::class, 'preview'])->name('preview');
        Route::post('commit', [DataTransferController::class, 'commit'])->name('commit');
        Route::get('{dataset}/template', [DataTransferController::class, 'template'])->name('template');
        Route::get('{dataset}/export', [DataTransferController::class, 'export'])->name('export');
    });

    Route::prefix('properties')->name('properties.')->group(function () {
        Route::get('/', [PropertyController::class, 'index'])->name('index')->middleware('permission:properties.view');
        Route::post('/', [PropertyController::class, 'store'])->name('store')->middleware('permission:properties.create');
        Route::get('transfer/import', [DataTransferController::class, 'importPage'])
            ->defaults('dataset', 'properties')
            ->name('transfer.import')
            ->middleware('permission:properties.import');
        Route::get('transfer/export', [DataTransferController::class, 'exportPage'])
            ->defaults('dataset', 'properties')
            ->name('transfer.export')
            ->middleware('permission:properties.export');

        Route::scopeBindings()->group(function () {
            Route::prefix('{property}')->group(function () {
                Route::get('/', [PropertyController::class, 'show'])->name('show')->middleware('permission:properties.view');
                Route::put('/', [PropertyController::class, 'update'])->name('update')->middleware('permission:properties.update');
                Route::delete('/', [PropertyController::class, 'destroy'])->name('destroy')->middleware('permission:properties.delete');
                Route::post('restore', [PropertyController::class, 'restore'])->name('restore')->middleware('permission:properties.update');
                Route::get('leases', PropertyLeasesController::class)->name('workspace.leases')->middleware('permission:properties.view');
                Route::get('documents', PropertyDocumentsController::class)->name('workspace.documents')->middleware('permission:properties.view');

                Route::prefix('unit-types')->name('unit-types.')->group(function () {
                    Route::get('/', [PropertyUnitTypeController::class, 'index'])->name('index')->middleware('permission:properties.view');
                    Route::post('/', [PropertyUnitTypeController::class, 'store'])->name('store')->middleware('permission:properties.update');

                    Route::prefix('{unitType}')->group(function () {
                        Route::put('/', [PropertyUnitTypeController::class, 'update'])->name('update')->middleware('permission:properties.update');
                        Route::post('deactivate', [PropertyUnitTypeController::class, 'deactivate'])->name('deactivate')->middleware('permission:properties.update');
                        Route::post('restore', [PropertyUnitTypeController::class, 'restore'])->name('restore')->middleware('permission:properties.update');

                        Route::prefix('gallery')->name('gallery.')->group(function () {
                            Route::post('/', [UnitTypeMediaController::class, 'store'])->name('store')->middleware('permission:properties.update');
                            Route::post('reorder', [UnitTypeMediaController::class, 'reorder'])->name('reorder')->middleware('permission:properties.update');
                            Route::get('{media}', [UnitTypeMediaController::class, 'show'])->name('show')->whereNumber('media')->middleware('permission:properties.view');
                            Route::patch('{media}', [UnitTypeMediaController::class, 'update'])->name('update')->whereNumber('media')->middleware('permission:properties.update');
                            Route::delete('{media}', [UnitTypeMediaController::class, 'destroy'])->name('destroy')->whereNumber('media')->middleware('permission:properties.update');
                        });
                    });
                });

                Route::post('amenities', [PropertyAmenityController::class, 'store'])->name('amenities.store')->middleware('permission:properties.update');
                Route::put('facilities', [PropertyAmenityController::class, 'syncProperty'])->name('facilities.update')->middleware('permission:properties.update');
                Route::withoutScopedBindings()->prefix('amenities/{amenity}')->whereNumber('amenity')->group(function () {
                    Route::post('deactivate', [PropertyAmenityController::class, 'deactivate'])->name('amenities.deactivate')->middleware('permission:properties.update');
                    Route::post('restore', [PropertyAmenityController::class, 'restore'])->name('amenities.restore')->middleware('permission:properties.update');
                });

                Route::prefix('gallery')->name('gallery.')->group(function () {
                    Route::post('/', [PropertyMediaController::class, 'store'])->name('store')->middleware('permission:properties.update');
                    Route::post('reorder', [PropertyMediaController::class, 'reorder'])->name('reorder')->middleware('permission:properties.update');
                    Route::get('{media}', [PropertyMediaController::class, 'show'])->name('show')->whereNumber('media')->middleware('permission:properties.view');
                    Route::patch('{media}', [PropertyMediaController::class, 'update'])->name('update')->whereNumber('media')->middleware('permission:properties.update');
                    Route::delete('{media}', [PropertyMediaController::class, 'destroy'])->name('destroy')->whereNumber('media')->middleware('permission:properties.update');
                });

                Route::prefix('units')->name('units.')->group(function () {
                    Route::get('/', [UnitController::class, 'index'])->name('index')->middleware('permission:units.view');
                    Route::post('/', [UnitController::class, 'store'])->name('store')->middleware('permission:units.create');
                    Route::get('transfer/import', [DataTransferController::class, 'importPage'])
                        ->defaults('dataset', 'units')
                        ->name('transfer.import')
                        ->middleware('permission:units.import');
                    Route::post('transfer/preview', [DataTransferController::class, 'preview'])
                        ->defaults('dataset', 'units')
                        ->name('transfer.preview')
                        ->middleware('permission:units.import');
                    Route::post('transfer/commit', [DataTransferController::class, 'commit'])
                        ->defaults('dataset', 'units')
                        ->name('transfer.commit')
                        ->middleware('permission:units.import');
                    Route::get('transfer/export', [DataTransferController::class, 'exportPage'])
                        ->defaults('dataset', 'units')
                        ->name('transfer.export')
                        ->middleware('permission:units.export');

                    Route::prefix('{unit}')->group(function () {
                        Route::get('/', [UnitController::class, 'show'])->name('show')->middleware('permission:units.view');
                        Route::put('/', [UnitController::class, 'update'])->name('update')->middleware('permission:units.update');
                        Route::delete('/', [UnitController::class, 'destroy'])->name('destroy')->middleware('permission:units.delete');
                        Route::post('restore', [UnitController::class, 'restore'])->name('restore')->withTrashed()->middleware('permission:units.update');
                        Route::get('rates/transfer/import', [DataTransferController::class, 'importPage'])
                            ->defaults('dataset', 'unit-rates')
                            ->name('rates.transfer.import')
                            ->middleware('permission:unit-rates.import');
                        Route::post('rates/transfer/preview', [DataTransferController::class, 'preview'])
                            ->defaults('dataset', 'unit-rates')
                            ->name('rates.transfer.preview')
                            ->middleware('permission:unit-rates.import');
                        Route::post('rates/transfer/commit', [DataTransferController::class, 'commit'])
                            ->defaults('dataset', 'unit-rates')
                            ->name('rates.transfer.commit')
                            ->middleware('permission:unit-rates.import');
                        Route::get('rates/transfer/export', [DataTransferController::class, 'exportPage'])
                            ->defaults('dataset', 'unit-rates')
                            ->name('rates.transfer.export')
                            ->middleware('permission:unit-rates.export');
                        Route::get('rates', [UnitController::class, 'rates'])->name('rates')->middleware('permission:units.view');
                        Route::get('utilities', [UnitUtilityController::class, 'index'])->name('utilities')->middleware('permission:units.view');
                        Route::post('utilities/meters', [UnitUtilityController::class, 'storeMeter'])
                            ->name('utilities.meters.store')
                            ->middleware('permission:units.update');
                        Route::put('utilities/meters/{meter}', [UnitUtilityController::class, 'updateMeter'])
                            ->name('utilities.meters.update')
                            ->middleware('permission:units.update');
                        Route::post('utilities/meters/{meter}/readings', [UnitUtilityController::class, 'storeReading'])
                            ->name('utilities.readings.store')
                            ->middleware('permission:units.update');
                        Route::put('utilities/meters/{meter}/readings/{reading}', [UnitUtilityController::class, 'updateReading'])
                            ->name('utilities.readings.update')
                            ->middleware('permission:units.update');
                        Route::delete('utilities/meters/{meter}/readings/{reading}', [UnitUtilityController::class, 'destroyReading'])
                            ->name('utilities.readings.destroy')
                            ->middleware('permission:units.update');
                        Route::post('utilities/meters/{meter}/readings/{reading}/correction', [UnitUtilityController::class, 'storeCorrection'])
                            ->name('utilities.readings.corrections.store')
                            ->middleware('permission:units.update');
                        Route::get('maintenance-history', [UnitController::class, 'maintenanceHistory'])
                            ->name('maintenance-history')
                            ->middleware('permission:maintenance-tickets.view');
                        Route::get('lease-history', [UnitController::class, 'leaseHistory'])
                            ->name('lease-history')
                            ->middleware('permission:leases.view');

                        Route::prefix('leases')->name('leases.')->group(function () {
                            Route::get('/', [LeaseController::class, 'index'])->name('index')->middleware('permission:leases.view');
                            Route::post('/', [LeaseController::class, 'store'])->name('store')->middleware('permission:leases.create');
                            Route::put('{lease}', [LeaseController::class, 'update'])->name('update')->middleware('permission:leases.update');
                            Route::delete('{lease}', [LeaseController::class, 'destroy'])->name('destroy')->middleware('permission:leases.delete');
                            Route::post('{lease}/move', [LeaseController::class, 'move'])->name('move')->middleware('permission:leases.move');
                        });
                    });
                });
            });
        });
    });

    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index')->middleware('permission:tenants.view');
        Route::post('/', [TenantController::class, 'store'])->name('store')->middleware('permission:tenants.create');
        Route::get('transfer/import', [DataTransferController::class, 'importPage'])
            ->defaults('dataset', 'tenants')
            ->name('transfer.import')
            ->middleware('permission:tenants.import');
        Route::get('transfer/export', [DataTransferController::class, 'exportPage'])
            ->defaults('dataset', 'tenants')
            ->name('transfer.export')
            ->middleware('permission:tenants.export');

        Route::prefix('{tenant}')->whereNumber('tenant')->group(function () {
            Route::get('/', [TenantController::class, 'show'])->name('show')->middleware('permission:tenants.view');
            Route::put('/', [TenantController::class, 'update'])->name('update')->middleware('permission:tenants.update');
            Route::delete('/', [TenantController::class, 'destroy'])->name('destroy')->middleware('permission:tenants.delete');
            Route::post('restore', [TenantController::class, 'restore'])->name('restore')->withTrashed()->middleware('permission:tenants.update');
            Route::get('leases', [TenantController::class, 'leases'])->name('workspace.leases')->middleware('permission:tenants.view');
            Route::get('documents', [TenantController::class, 'documents'])->name('workspace.documents')->middleware('permission:tenants.view');
            Route::post('assign-unit', [TenantController::class, 'assignUnit'])->name('assign-unit')->middleware('permission:tenants.update');
            Route::post('invite', [TenantController::class, 'invite'])->name('invite')->middleware('permission:tenants.invite');
            Route::post('resend-invitation', [TenantController::class, 'resendInvitation'])->name('resend-invitation')->middleware('permission:tenants.invite');
            Route::post('disable-access', [TenantController::class, 'disableAccess'])->name('disable-access')->middleware('permission:tenants.invite');

            Route::prefix('documents')->name('documents.')->group(function () {
                Route::post('/', [TenantDocumentController::class, 'store'])->name('store')->middleware('permission:tenants.update');
                Route::get('{document}', [TenantDocumentController::class, 'show'])->name('show');
                Route::delete('{document}', [TenantDocumentController::class, 'destroy'])->name('destroy')->middleware('permission:tenants.update');
            });
        });
    });

    Route::prefix('leases')->name('leases.')->group(function () {
        Route::get('/', [LeaseController::class, 'globalIndex'])->name('index')->middleware('permission:leases.view');

        Route::prefix('{lease}')->whereNumber('lease')->group(function () {
            Route::get('/', [LeaseController::class, 'show'])->name('show')->middleware('permission:leases.view');
            Route::get('documents', [LeaseController::class, 'documents'])->name('workspace.documents')->middleware('permission:leases.view');
            Route::get('invoices', [LeaseInvoiceController::class, 'index'])->name('workspace.invoices')->middleware('permission:leases.view');
            Route::get('invoices/{invoice}', [LeaseInvoiceController::class, 'show'])->name('workspace.invoices.show')->middleware('permission:leases.view');
            Route::post('invoices/{invoice}/payment-attempts/{paymentAttempt}/recheck', [PaymentAttemptController::class, 'recheck'])
                ->name('workspace.invoices.payment-attempts.recheck')
                ->scopeBindings()
                ->middleware('permission:payments.verify');
            Route::get('invoices/{invoice}/print', [LeaseInvoiceController::class, 'print'])->name('workspace.invoices.print')->middleware('permission:leases.view');
            Route::get('invoices/{invoice}/download', [LeaseInvoiceController::class, 'download'])->name('workspace.invoices.download')->middleware('permission:leases.view');
            Route::get('rent-schedule', LeaseRentScheduleController::class)->name('rent-schedule')->middleware('permission:leases.view');
            Route::post('move-out', [LeaseController::class, 'moveOut'])->name('move-out')->middleware('permission:leases.move_out');
            Route::post('renew', [LeaseController::class, 'renew'])->name('renew')->middleware('permission:leases.renew');

            Route::prefix('payments')->group(function () {
                Route::get('/', [LeaseController::class, 'payments'])->name('workspace.payments')->middleware('permission:leases.view');
                Route::post('/', [PaymentController::class, 'store'])->name('payments.store')->middleware('permission:payments.create');
            });
        });
    });

    Route::prefix('payments/{payment}')->name('payments.')->scopeBindings()->group(function () {
        Route::get('proof/{proof}', [PaymentController::class, 'proof'])->name('proof');
        Route::post('verify', [PaymentController::class, 'verify'])->name('verify')->middleware('permission:payments.verify');
    });

    Route::prefix('maintenance-tickets')->name('maintenance-tickets.')->group(function () {
        Route::get('/', [MaintenanceTicketController::class, 'index'])->name('index')->middleware('permission:maintenance-tickets.view');
        Route::post('/', [MaintenanceTicketController::class, 'store'])->name('store')->middleware('permission:maintenance-tickets.create');

        Route::prefix('{ticket}')->whereNumber('ticket')->group(function () {
            Route::get('/', [MaintenanceTicketController::class, 'show'])->name('show')->middleware('permission:maintenance-tickets.view');
            Route::put('/', [MaintenanceTicketController::class, 'update'])->name('update')->middleware('permission:maintenance-tickets.update');
            Route::delete('/', [MaintenanceTicketController::class, 'destroy'])->name('destroy')->middleware('permission:maintenance-tickets.delete');
            Route::post('assign', [MaintenanceTicketController::class, 'assign'])->name('assign');
        });
    });

    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index')->middleware('permission:expenses.view');
        Route::post('/', [ExpenseController::class, 'store'])->name('store')->middleware('permission:expenses.create');
        Route::get('transfer/import', [DataTransferController::class, 'importPage'])
            ->defaults('dataset', 'expenses')
            ->name('transfer.import')
            ->middleware('permission:expenses.import');
        Route::get('transfer/export', [DataTransferController::class, 'exportPage'])
            ->defaults('dataset', 'expenses')
            ->name('transfer.export')
            ->middleware('permission:expenses.export');

        Route::prefix('{expense}')->whereNumber('expense')->group(function () {
            Route::put('/', [ExpenseController::class, 'update'])->name('update')->middleware('permission:expenses.update');
            Route::delete('/', [ExpenseController::class, 'destroy'])->name('destroy')->middleware('permission:expenses.delete');
            Route::get('receipt', [ExpenseController::class, 'receipt'])->name('receipt')->middleware('permission:expenses.view');
        });
    });

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index')->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])->name('store')->middleware('permission:users.create');

        Route::prefix('{user}')->whereNumber('user')->group(function () {
            Route::get('/', [UserController::class, 'show'])->name('show')->middleware('permission:users.view');
            Route::put('/', [UserController::class, 'update'])->name('update')->middleware('permission:users.update');
            Route::delete('/', [UserController::class, 'destroy'])->name('destroy')->middleware('permission:users.delete');
            Route::post('reset-password', [UserController::class, 'resetPassword'])->name('reset-password')->middleware('permission:users.reset_password');
            Route::post('resend-invitation', [UserController::class, 'resendInvitation'])->name('resend-invitation')->middleware('permission:users.resend_invitation');
        });
    });

    Route::middleware('role:owner')->group(function () {
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('create', [RoleController::class, 'create'])->name('create');
            Route::post('/', [RoleController::class, 'store'])->name('store');

            Route::prefix('{role}')->whereNumber('role')->group(function () {
                Route::get('/', [RoleController::class, 'show'])->name('show');
                Route::get('edit', [RoleController::class, 'edit'])->name('edit');
                Route::put('/', [RoleController::class, 'update'])->name('update');
                Route::delete('/', [RoleController::class, 'destroy'])->name('destroy');
                Route::post('clone', [RoleController::class, 'clone'])->name('clone');
            });
        });

        Route::post('leases/{lease}/send-reminder', [LeaseController::class, 'sendReminder'])
            ->middleware('can:reminders.send')
            ->name('leases.send-reminder');
    });
});

require __DIR__.'/settings.php';
