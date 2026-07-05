<?php

use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\RentController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\LeaseRentScheduleController;
use App\Http\Controllers\MaintenanceTicketController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyDocumentsController;
use App\Http\Controllers\PropertyLeasesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantDocumentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('invitations')->name('users.invitations.')->middleware('guest')->group(function () {
    Route::get('{token}', [UserController::class, 'acceptInvitation'])->name('accept');
    Route::post('accept', [UserController::class, 'completeInvitation'])->name('complete');
});

Route::middleware(['auth', 'verified', 'permission:dashboard.view'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', OverviewController::class)->name('dashboard');
        Route::get('rent', RentController::class)->name('dashboard.rent');
    });

    Route::prefix('properties')->name('properties.')->group(function () {
        Route::get('/', [PropertyController::class, 'index'])->name('index')->middleware('permission:properties.view');
        Route::post('/', [PropertyController::class, 'store'])->name('store')->middleware('permission:properties.create');

        Route::scopeBindings()->group(function () {
            Route::prefix('{property}')->group(function () {
                Route::get('/', [PropertyController::class, 'show'])->name('show')->middleware('permission:properties.view');
                Route::put('/', [PropertyController::class, 'update'])->name('update')->middleware('permission:properties.update');
                Route::delete('/', [PropertyController::class, 'destroy'])->name('destroy')->middleware('permission:properties.delete');
                Route::get('leases', PropertyLeasesController::class)->name('workspace.leases')->middleware('permission:properties.view');
                Route::get('documents', PropertyDocumentsController::class)->name('workspace.documents')->middleware('permission:properties.view');

                Route::prefix('rooms')->name('rooms.')->group(function () {
                    Route::get('/', [RoomController::class, 'index'])->name('index')->middleware('permission:rooms.view');
                    Route::post('/', [RoomController::class, 'store'])->name('store')->middleware('permission:rooms.create');

                    Route::prefix('{room}')->group(function () {
                        Route::get('/', [RoomController::class, 'show'])->name('show')->middleware('permission:rooms.view');
                        Route::put('/', [RoomController::class, 'update'])->name('update')->middleware('permission:rooms.update');
                        Route::delete('/', [RoomController::class, 'destroy'])->name('destroy')->middleware('permission:rooms.delete');
                        Route::get('maintenance-history', [RoomController::class, 'maintenanceHistory'])
                            ->name('maintenance-history')
                            ->middleware('permission:maintenance-tickets.view');
                        Route::get('lease-history', [RoomController::class, 'leaseHistory'])
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

        Route::prefix('{tenant}')->whereNumber('tenant')->group(function () {
            Route::get('/', [TenantController::class, 'show'])->name('show')->middleware('permission:tenants.view');
            Route::put('/', [TenantController::class, 'update'])->name('update')->middleware('permission:tenants.update');
            Route::delete('/', [TenantController::class, 'destroy'])->name('destroy')->middleware('permission:tenants.delete');
            Route::get('leases', [TenantController::class, 'leases'])->name('workspace.leases')->middleware('permission:tenants.view');
            Route::get('documents', [TenantController::class, 'documents'])->name('workspace.documents')->middleware('permission:tenants.view');
            Route::post('assign-room', [TenantController::class, 'assignRoom'])->name('assign-room')->middleware('permission:tenants.update');

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
            Route::get('rent-schedule', LeaseRentScheduleController::class)->name('rent-schedule')->middleware('permission:leases.view');
            Route::post('move-out', [LeaseController::class, 'moveOut'])->name('move-out')->middleware('permission:leases.move_out');
            Route::post('renew', [LeaseController::class, 'renew'])->name('renew')->middleware('permission:leases.renew');

            Route::prefix('payments')->group(function () {
                Route::get('/', [LeaseController::class, 'payments'])->name('workspace.payments')->middleware('permission:leases.view');
                Route::post('/', [PaymentController::class, 'store'])->name('payments.store')->middleware('permission:payments.create');
            });
        });
    });

    Route::prefix('payments/{payment}')->name('payments.')->group(function () {
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
