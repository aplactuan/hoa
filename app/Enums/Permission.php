<?php

namespace App\Enums;

enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case GateView = 'gate.view';
    case PropertiesView = 'properties.view';
    case OwnersView = 'owners.view';
    case ResidentsView = 'residents.view';
    case FamilyView = 'family.view';
    case HouseStaffView = 'house-staff.view';
    case VehiclesView = 'vehicles.view';
    case StatementsView = 'statements.view';
    case ReceiptsView = 'receipts.view';
    case ChargesView = 'charges.view';
    case AccountSummaryView = 'account-summary.view';
    case ViolationsView = 'violations.view';
    case RequestsView = 'requests.view';
    case FacilitiesView = 'facilities.view';
    case TransactionsView = 'transactions.view';
    case CorrespondenceView = 'correspondence.view';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';
    case SettingsManage = 'settings.manage';
    case MasterDataManage = 'master-data.manage';
    case DevicesManage = 'devices.manage';
    case FinancialView = 'financial.view';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'View the dashboard',
            self::GateView => 'Use gate check',
            self::PropertiesView => 'View properties',
            self::OwnersView => 'View owners',
            self::ResidentsView => 'View residents',
            self::FamilyView => 'View family members',
            self::HouseStaffView => 'View house staff',
            self::VehiclesView => 'View vehicles and stickers',
            self::StatementsView => 'View statements of account',
            self::ReceiptsView => 'View official receipts',
            self::ChargesView => 'View charges',
            self::AccountSummaryView => 'View owner account summaries',
            self::ViolationsView => 'View violations',
            self::RequestsView => 'View requests',
            self::FacilitiesView => 'View facilities',
            self::TransactionsView => 'View office transactions',
            self::CorrespondenceView => 'View correspondence',
            self::UsersView => 'View users',
            self::UsersManage => 'Create and update users',
            self::RolesView => 'View roles',
            self::AuditView => 'View the audit log',
            self::SettingsManage => 'Manage association settings',
            self::MasterDataManage => 'Manage lookup lists',
            self::DevicesManage => 'Manage devices',
            self::FinancialView => 'View money amounts',
        };
    }
}
