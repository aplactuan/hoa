<?php

use App\Enums\Permission;
use App\Enums\RoleName;

return [

    /*
    |--------------------------------------------------------------------------
    | Role matrix
    |--------------------------------------------------------------------------
    |
    | Five roles until the board signs a replacement. Administrator sees every
    | module. Treasurer sees records and collections, including money. Office
    | staff sees records and community work, without money. Security Guard
    | sees Gate Check only. Owners use a property-scoped self-service portal.
    |
    */

    'roles' => [
        RoleName::Administrator->value => [
            'label' => RoleName::Administrator->label(),
            'permissions' => array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::cases(),
            ),
        ],
        RoleName::Treasurer->value => [
            'label' => RoleName::Treasurer->label(),
            'permissions' => [
                Permission::DashboardView->value,
                Permission::PropertiesView->value,
                Permission::OwnersView->value,
                Permission::StatementsView->value,
                Permission::ReceiptsView->value,
                Permission::ChargesView->value,
                Permission::AccountSummaryView->value,
                Permission::FacilitiesView->value,
                Permission::TransactionsView->value,
                Permission::CorrespondenceView->value,
                Permission::FinancialView->value,
            ],
        ],
        RoleName::OfficeStaff->value => [
            'label' => RoleName::OfficeStaff->label(),
            'permissions' => [
                Permission::DashboardView->value,
                Permission::PropertiesView->value,
                Permission::OwnersView->value,
                Permission::ResidentsView->value,
                Permission::FamilyView->value,
                Permission::HouseStaffView->value,
                Permission::VehiclesView->value,
                Permission::StatementsView->value,
                Permission::ReceiptsView->value,
                Permission::ChargesView->value,
                Permission::AccountSummaryView->value,
                Permission::FinancialView->value,
                Permission::ViolationsView->value,
                Permission::RequestsView->value,
                Permission::FacilitiesView->value,
                Permission::TransactionsView->value,
                Permission::CorrespondenceView->value,
                Permission::OwnerChangesManage->value,
            ],
        ],
        RoleName::SecurityGuard->value => [
            'label' => RoleName::SecurityGuard->label(),
            'permissions' => [
                Permission::GateView->value,
                Permission::PropertiesView->value,
                Permission::RequestsView->value,
                Permission::ResidentsView->value,
                Permission::FamilyView->value,
                Permission::HouseStaffView->value,
                Permission::VehiclesView->value,
            ],
        ],
        RoleName::Owner->value => [
            'label' => RoleName::Owner->label(),
            'permissions' => [
                Permission::OwnerPortalView->value,
                Permission::FinancialView->value,
            ],
        ],
    ],

    'navigation' => [
        [
            'heading' => 'Today',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'permission' => Permission::DashboardView->value, 'badge' => null],
                ['label' => 'Gate Check', 'route' => 'gate-check', 'icon' => 'shield-check', 'permission' => Permission::GateView->value, 'badge' => null],
                ['label' => 'My Dashboard', 'route' => 'owner.dashboard', 'icon' => 'home', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
            ],
        ],
        [
            'heading' => 'Records',
            'items' => [
                ['label' => 'Properties', 'route' => 'properties.index', 'icon' => 'building-office-2', 'permission' => Permission::PropertiesView->value, 'badge' => null],
                ['label' => 'Owners', 'route' => 'owners.index', 'icon' => 'user-group', 'permission' => Permission::OwnersView->value, 'badge' => null],
                ['label' => 'Residents', 'route' => 'residents.index', 'icon' => 'users', 'permission' => Permission::ResidentsView->value, 'badge' => null],
                ['label' => 'Family Members', 'route' => 'family.index', 'icon' => 'user-group', 'permission' => Permission::FamilyView->value, 'badge' => null],
                ['label' => 'House Staff', 'route' => 'house-staff.index', 'icon' => 'identification', 'permission' => Permission::HouseStaffView->value, 'badge' => null],
                ['label' => 'Vehicles & Stickers', 'route' => 'vehicles.index', 'icon' => 'truck', 'permission' => Permission::VehiclesView->value, 'badge' => null],
                ['label' => 'My Property', 'route' => 'owner.property', 'icon' => 'building-office-2', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
            ],
        ],
        [
            'heading' => 'Collections',
            'items' => [
                ['label' => 'Statements', 'route' => 'statements.index', 'icon' => 'document-text', 'permission' => Permission::StatementsView->value, 'badge' => null],
                ['label' => 'Receipts', 'route' => 'receipts.index', 'icon' => 'banknotes', 'permission' => Permission::ReceiptsView->value, 'badge' => null],
                ['label' => 'Charges', 'route' => 'charges.index', 'icon' => 'credit-card', 'permission' => Permission::ChargesView->value, 'badge' => null],
                ['label' => 'Owner Account Summary', 'route' => 'account-summary.index', 'icon' => 'document-chart-bar', 'permission' => Permission::AccountSummaryView->value, 'badge' => null],
                ['label' => 'My Account', 'route' => 'owner.account', 'icon' => 'document-chart-bar', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
            ],
        ],
        [
            'heading' => 'Community',
            'items' => [
                ['label' => 'Transactions', 'route' => 'transactions.index', 'icon' => 'document-check', 'permission' => Permission::TransactionsView->value, 'badge' => null],
                ['label' => 'Violations', 'route' => 'violations.index', 'icon' => 'exclamation-triangle', 'permission' => Permission::ViolationsView->value, 'badge' => null],
                ['label' => 'Requests', 'route' => 'requests.index', 'icon' => 'inbox', 'permission' => Permission::RequestsView->value, 'badge' => null],
                ['label' => 'Facilities', 'route' => 'facilities.index', 'icon' => 'calendar-days', 'permission' => Permission::FacilitiesView->value, 'badge' => null],
                ['label' => 'Correspondence', 'route' => 'correspondence.index', 'icon' => 'envelope', 'permission' => Permission::CorrespondenceView->value, 'badge' => null],
                ['label' => 'My Requests', 'route' => 'owner.requests', 'icon' => 'inbox', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
                ['label' => 'Facility Bookings', 'route' => 'owner.facilities', 'icon' => 'calendar-days', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
                ['label' => 'My Notices', 'route' => 'owner.notices', 'icon' => 'envelope', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
                ['label' => 'Change Requests', 'route' => 'owner.changes', 'icon' => 'clipboard-document-list', 'permission' => Permission::OwnerPortalView->value, 'roles' => [RoleName::Owner->value], 'badge' => null],
                ['label' => 'Owner Changes', 'route' => 'owner-changes.index', 'icon' => 'clipboard-document-list', 'permission' => Permission::OwnerChangesManage->value, 'badge' => null],
            ],
        ],
        [
            'heading' => 'Administration',
            'items' => [
                ['label' => 'Users', 'route' => 'users.index', 'icon' => 'users', 'permission' => Permission::UsersView->value, 'badge' => 'users'],
                ['label' => 'Roles', 'route' => 'roles.index', 'icon' => 'key', 'permission' => Permission::RolesView->value, 'badge' => null],
                ['label' => 'Audit Log', 'route' => 'audit-log.index', 'icon' => 'clipboard-document-list', 'permission' => Permission::AuditView->value, 'badge' => null],
                ['label' => 'Settings', 'route' => 'settings.hoa', 'icon' => 'cog-6-tooth', 'permission' => Permission::SettingsManage->value, 'badge' => null],
                ['label' => 'Master Data', 'route' => 'master-data.index', 'icon' => 'queue-list', 'permission' => Permission::MasterDataManage->value, 'badge' => null],
                ['label' => 'Imports', 'route' => 'imports.index', 'icon' => 'arrow-up-tray', 'permission' => Permission::MasterDataManage->value, 'badge' => null],
                ['label' => 'Devices', 'route' => 'devices.index', 'icon' => 'computer-desktop', 'permission' => Permission::DevicesManage->value, 'badge' => null],
            ],
        ],
    ],

    'financial_attributes' => [
        'dues_rate_per_sqm',
        'default_fee',
        'default_fine',
        'fee',
        'deposit',
        'amount',
        'balance',
        'penalty_amount',
    ],

];
