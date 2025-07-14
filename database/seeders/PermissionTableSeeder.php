<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            'admin' => [
                'role-list',
                'role-create',
                'role-edit',
                'role-delete',
                'customer-list',
                'customer-create',
                'customer-show',
                'customer-edit',
                'customer-delete',
                'customer-action',
                'merchant-list',
                'merchant-create',
                'merchant-edit',
                'merchant-show',
                'merchant-delete',
                'merchant-bookings',
                'vehicle-list',
                'vehicle-create',
                'vehicle-show',
                'vehicle-edit',
                'vehicle-delete',
                'vehicle-action',
                'vehicle-deckfare',
                'cabin-list',
                'cabin-create',
                'cabin-edit',
                'cabin-delete',
                'ghat-list',
                'ghat-create',
                'ghat-edit',
                'ghat-delete',
                'route-list',
                'route-create',
                'route-edit',
                'route-mapping',
                'schedule-list',
                'schedule-create',
                'schedule-edit',
                'schedule-update',
                'schedule-show',
                'booking-list',
                'booking-ticket',
                'booking-cabin',
                'booking-sofa',
                'booking-seat',
                'booking-quick',
                'booking-assign-honorium',
                'user-list',
                'user-create',
                'user-edit',
                'user-delete',
                'user-approve',
                'user-suspend',
                'page-list',
                'page-create',
                'page-edit',
                'page-update',
                'page-delete',
                'blog-list',
                'blog-create',
                'blog-edit',
                'blog-update',
                'blog-delete',
                'category-list',
                'category-create',
                'category-edit',
                'category-update',
                'category-delete',
                'coupon-list',
                'coupon-show',
                'coupon-create',
                'coupon-edit',
                'coupon-delete',
                'discount-list',
                'discount-create',
                'discount-edit',
                'discount-action',
                'sponsor-list',
                'sponsor-create',
                'sponsor-edit',
                'sponsor-action',
                'type-list',
                'type-create',
                'type-edit',
                'type-show',
                'type-delete',
                'schedule-action',
                'booking-view',
                'cancellation-list',
                'cancellation-show',
                'cancellation-action',
                'coupon-action',
                'coupon-broadcust',
                'permission-list',
                'settings-manage'
            ],
            'merchant' => [
                'merchants-statistics',
                'vehicle-manage',
                'vehicle-add',
                'vehicle-update',
                'vehicle-view',
                'vehicle-reservation',
                'vehicle-info',
                'vehicle-statistics',
                'vehicle-deckfares',
                'vehicle-actions',
                'cabins-list',
                'cabins-add',
                'cabins-update',
                'bookings-list',
                'bookings-overview',
                'bookings-cancel',
                'payments-list',
                'supervisor-add',
                'supervisor-edit',
                'supervisor-suspend',
                'supervisor-active',
                'supervisor-assign',
                'schedule-manage',
                'schedule-view',
                'schedule-assign',
                'schedule-statistics',
                'schedule-change',
                'traveller-manage',
                'traveller-add',
                'traveller-update',
                'traveller-show',
                'traveller-activate',
                'traveller-suspend',
                'traveller-action',
                'traveller-delete',
                'profile-view',
                'profile-update',
                'profile-picture-upload',
                'setting-manage',
                'setting-update',
                'setting-view',
                'accounts-manage',
                'accounts-statistics',
                'accounts-report',
                'inventory-manage',
                'inventory-statistics',
                'inventory-report',
                'other-ticket-sell',
                'other-cabin-booking',
                'other-quick-book',
                'other-vehicle-reservation',
                'supervisor-view',
                'permissions-list'
            ]
        ];

        $admin = Role::where('name', 'admin')->first();
        $merchant = Role::where('name', 'merchant')->first();
        foreach ($permissions as $key => $permission) {
            foreach ($permission as $p) {
                $perm = Permission::create(['name' => $p, 'type' => $key]);
                if ($key == 'admin') {
                    $admin->givePermissionTo($perm);
                } elseif ($key == 'merchant') {
                    $merchant->givePermissionTo($perm);
                }
            }
        }
    }
}
