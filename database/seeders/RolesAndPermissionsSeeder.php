<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brief §2 roles + a permission set covering every module in Brief §3. Route/controller-level
 * enforcement (`->can(...)` middleware, Policies) is wired progressively from Phase 4 onward —
 * this seeder's job is exactly "roles + permissions", per the Phase 3 spec.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'bookings.view',
            'bookings.manage',
            'bookings.view_own',
            'pos.sell',
            'pos.refund',
            'catalog.manage',
            'cms.manage',
            'crm.manage',
            'reports.view',
            'settings.view',
            'settings.manage',
            'users.manage',
            'audit.view',
            // Added in Phase 4 sub-step 2: staff records/schedules (Staff, StaffWorkingHour,
            // StaffTimeOff) don't map cleanly onto any existing permission — 'settings.manage' would
            // conflate salon config with HR data, and 'bookings.manage' doesn't cover schedules.
            'staff.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Without this, syncPermissions() below resolves names against spatie/permission's cached
        // registry, which was built (and cached empty/stale) before these rows existed in this run.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate('super-admin');
        $superAdmin->syncPermissions($permissions); // explicit for auditability; Gate::before also covers new ones

        Role::findOrCreate('admin')->syncPermissions([
            'bookings.view', 'bookings.manage',
            'pos.sell', 'pos.refund',
            'catalog.manage', 'cms.manage', 'crm.manage',
            'reports.view',
            'settings.view', // read-only per Brief §2 — no settings.manage
            'staff.manage',
        ]);

        Role::findOrCreate('receptionist')->syncPermissions([
            'bookings.view', 'bookings.manage',
            'pos.sell',
            'crm.manage',
        ]);

        Role::findOrCreate('staff')->syncPermissions([
            'bookings.view_own',
        ]);

        // No admin-panel permissions — customers only ever touch their own public-facing account.
        Role::findOrCreate('customer');

        $this->assignSeededUsers();
    }

    /**
     * Assign roles to the users the Phase 2 seeder already created, so the seeded dataset is
     * immediately usable for manual testing.
     */
    private function assignSeededUsers(): void
    {
        User::where('email', 'lookssmartbeautysalon@gmail.com')
            ->first()
            ?->assignRole('super-admin');

        User::whereHas('staff')->each(fn (User $user) => $user->assignRole('staff'));

        User::whereHas('customerProfile')->each(fn (User $user) => $user->assignRole('customer'));
    }
}
