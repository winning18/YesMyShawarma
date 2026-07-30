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
            'menu.toggle_availability',
            'reports.view_operational',
        ],
        'rider' => [
            'orders.view',
            'orders.advance_status',
            'orders.claim',
        ],
        'manager' => [
            'orders.view',
            'orders.accept',
            'orders.reject',
            'orders.advance_status',
            'orders.assign_rider',
            'orders.void',
            'orders.discount',
            'menu.toggle_availability',
            'menu.edit_content',
            'reports.view_operational',
            'reports.view_financial',
            'promotions.manage',
            'customers.view',
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
            'menu.toggle_availability',
            'menu.edit_content',
            'menu.edit_price',
            'reports.view_operational',
            'reports.view_financial',
            'promotions.manage',
            'customers.view',
            'users.manage',
            'branches.manage',
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
