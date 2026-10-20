<?php

use App\Enums\Permission;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\PrintDocumentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('attachments/{attachment}', AttachmentController::class)->name('attachments.download');
    Route::get('statements/{duesStatement}/print', [PrintDocumentController::class, 'statement'])->name('statements.print');
    Route::get('receipts/{payment}/print', [PrintDocumentController::class, 'receipt'])->name('receipts.print');
    Route::view('dashboard', 'dashboard')
        ->middleware('permission:'.Permission::DashboardView->value)
        ->name('dashboard');

    Route::livewire('gate-check', 'pages::gate-check')
        ->middleware('permission:'.Permission::GateView->value)
        ->name('gate-check');

    Route::livewire('search', 'pages::search')->name('search');
    Route::livewire('notifications', 'pages::notifications')->name('notifications.index');
    Route::livewire('imports', 'pages::imports')
        ->middleware('permission:'.Permission::MasterDataManage->value)
        ->name('imports.index');

    Route::livewire('properties', 'pages::properties')
        ->middleware('permission:'.Permission::PropertiesView->value)
        ->name('properties.index');

    Route::livewire('properties/{property}', 'pages::property-profile')
        ->middleware('permission:'.Permission::PropertiesView->value)
        ->name('properties.show');

    Route::livewire('owners', 'pages::people')
        ->middleware('permission:'.Permission::OwnersView->value)
        ->name('owners.index');

    Route::livewire('residents', 'pages::people')
        ->middleware('permission:'.Permission::ResidentsView->value)
        ->name('residents.index');

    Route::livewire('family', 'pages::people')
        ->middleware('permission:'.Permission::FamilyView->value)
        ->name('family.index');

    Route::livewire('house-staff', 'pages::people')
        ->middleware('permission:'.Permission::HouseStaffView->value)
        ->name('house-staff.index');

    Route::livewire('vehicles', 'pages::people')
        ->middleware('permission:'.Permission::VehiclesView->value)
        ->name('vehicles.index');

    Route::livewire('statements', 'pages::statements')
        ->middleware('permission:'.Permission::StatementsView->value)
        ->name('statements.index');

    Route::livewire('receipts', 'pages::payments')
        ->middleware('permission:'.Permission::ReceiptsView->value)
        ->name('receipts.index');

    Route::livewire('charges', 'pages::charges')
        ->middleware('permission:'.Permission::ChargesView->value)
        ->name('charges.index');

    Route::livewire('account-summary', 'pages::account-summary')
        ->middleware('permission:'.Permission::AccountSummaryView->value)
        ->name('account-summary.index');

    Route::livewire('transactions', 'pages::office-records')
        ->middleware('permission:'.Permission::TransactionsView->value)
        ->name('transactions.index');

    Route::livewire('violations', 'pages::office-records')
        ->middleware('permission:'.Permission::ViolationsView->value)
        ->name('violations.index');

    Route::livewire('requests', 'pages::office-records')
        ->middleware('permission:'.Permission::RequestsView->value)
        ->name('requests.index');

    Route::livewire('facilities', 'pages::office-records')
        ->middleware('permission:'.Permission::FacilitiesView->value)
        ->name('facilities.index');

    Route::livewire('correspondence', 'pages::office-records')
        ->middleware('permission:'.Permission::CorrespondenceView->value)
        ->name('correspondence.index');

    Route::livewire('users', 'pages::users.index')
        ->middleware('permission:'.Permission::UsersView->value)
        ->name('users.index');

    Route::livewire('roles', 'pages::roles.index')
        ->middleware('permission:'.Permission::RolesView->value)
        ->name('roles.index');

    Route::livewire('audit-log', 'pages::audit-log')
        ->middleware('permission:'.Permission::AuditView->value)
        ->name('audit-log.index');

    Route::livewire('settings/association', 'pages::settings.hoa')
        ->middleware('permission:'.Permission::SettingsManage->value)
        ->name('settings.hoa');

    Route::livewire('master-data', 'pages::master-data')
        ->middleware('permission:'.Permission::MasterDataManage->value)
        ->name('master-data.index');

    Route::livewire('devices', 'pages::devices')
        ->middleware('permission:'.Permission::DevicesManage->value)
        ->name('devices.index');
});

require __DIR__.'/settings.php';
