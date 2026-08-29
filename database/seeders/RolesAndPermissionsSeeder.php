<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission matrix — mirrors .claude/rules/permissions.md exactly.
     * Update both together; a permission that exists in one but not the
     * other will drift.
     *
     * @var array<string, list<string>>
     */
    private array $matrix = [
        'staff' => [
            'orders.view',
            'orders.accept',
            'orders.reject',
            'orders.advance_status',
            'orders.assign_rider',
            'orders.create',
            'orders.refund_request',
            'menu.toggle_availability',
            'reports.view_operational',
        ],
        'rider' => [
            'orders.view',
            'orders.advance_status',
        ],
        // orders.refund (not the weaker orders.refund_request) — manager
        // has the exact same refund rights as owner: approve/deny a
        // staff-submitted request, or refund a customer directly with no
        // approval step at all. See payments.md's Refunds section.
        'manager' => [
            'orders.view',
            'orders.accept',
            'orders.reject',
            'orders.advance_status',
            'orders.assign_rider',
            'orders.void',
            'orders.discount',
            'orders.create',
            'orders.refund',
            'menu.toggle_availability',
            'menu.edit_content',
            'reports.view_operational',
            'reports.view_financial',
            'promotions.manage',
            'customers.view',
            'reviews.moderate',
            'users.transfer_branch',
            'dashboard.performance',
            'settings.manage',
        ],
        // Same operational permission set as 'manager' (including
        // orders.refund — see above), at every branch they're assigned to
        // (not one at a time via the branch switcher like a regular
        // manager — see PerformanceController's multi-branch aggregate),
        // plus the ability to create staff/rider accounts
        // (users.create_operational), which a regular manager still
        // cannot do at all.
        'general_manager' => [
            'orders.view',
            'orders.accept',
            'orders.reject',
            'orders.advance_status',
            'orders.assign_rider',
            'orders.void',
            'orders.discount',
            'orders.create',
            'orders.refund',
            'menu.toggle_availability',
            'menu.edit_content',
            'reports.view_operational',
            'reports.view_financial',
            'promotions.manage',
            'customers.view',
            'reviews.moderate',
            'users.transfer_branch',
            'users.create_operational',
            'dashboard.performance',
            'settings.manage',
        ],
        'owner' => [
            'orders.view',
            'orders.accept',
            'orders.reject',
            'orders.advance_status',
            'orders.assign_rider',
            'orders.void',
            'orders.refund',
            'orders.discount',
            'orders.create',
            'menu.toggle_availability',
            'menu.edit_content',
            'reports.view_operational',
            'reports.view_financial',
            'promotions.manage',
            'customers.view',
            'reviews.moderate',
            'users.manage',
            'users.transfer_branch',
            'branches.manage',
            'dashboard.performance',
            'settings.manage',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->matrix)->flatten()->unique();

        $permissions->each(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        foreach ($this->matrix as $roleName => $permissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($permissionNames);
        }
    }
}
