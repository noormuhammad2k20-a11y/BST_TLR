<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClothStore\Role;
use App\Models\ClothStore\Permission;

class ClothStoreRolesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'Dashboard' => ['View'],
            'Products' => ['View', 'Create', 'Edit', 'Delete'],
            'Stock' => ['View', 'Stock In', 'Stock Out', 'Adjustment', 'Transfer'],
            'Sales' => ['Create Sale', 'Edit Sale', 'Cancel Sale', 'Refund', 'Return'],
            'Customers' => ['View', 'Create', 'Edit', 'Delete', 'Collect Payment'],
            'Suppliers' => ['View', 'Create', 'Edit', 'Payment'],
            'Finance' => ['View Expenses', 'Create Expenses', 'View Payments'],
            'Settings' => ['View', 'Modify'],
            'Reports' => ['View', 'Export']
        ];

        $permModels = [];
        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                $name = $module . ' - ' . $action;
                $permModels[$name] = Permission::firstOrCreate([
                    'name' => $name,
                    'module' => $module
                ]);
            }
        }

        $roles = [
            'Super Admin',
            'Manager',
            'Cashier',
            'Inventory Staff',
            'Accountant'
        ];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            
            // Assign specific permissions (for simplicity in the seeder, Super Admin gets all)
            if ($roleName == 'Super Admin') {
                $role->permissions()->sync(collect($permModels)->pluck('id'));
            }
        }
    }
}
