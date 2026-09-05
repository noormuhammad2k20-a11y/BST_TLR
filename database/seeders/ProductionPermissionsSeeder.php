<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClothStore\{Permission,Role};

final class ProductionPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules=[
            'Dashboard'=>['View'],'Products'=>['View','Create','Edit','Delete'],
            'Stock'=>['View','Stock In','Stock Out','Adjustment'],
            'Sales'=>['View','Create Sale','Edit Sale','Cancel Sale','Refund','Return','Approve Return','Discount'],
            'Customers'=>['View','Create','Edit','Delete','Collect Payment'],
            'Finance'=>['View Expenses','Create Expenses','Edit Expenses','Delete Expenses','View Payments','Reverse Payment'],
            'Discounts'=>['View','Create','Edit','Delete'],'Settings'=>['View','Modify'],
            'Reports'=>['View','Export'],'Loyalty'=>['Manage'],
        ];
        $all=[];
        foreach ($modules as $module=>$actions) foreach ($actions as $action) {
            $name=$module.' - '.$action;
            $all[$name]=Permission::firstOrCreate(['name'=>$name],['module'=>$module])->id;
        }
        $ownerRole=Role::firstOrCreate(['name'=>'Super Admin'],['description'=>'Sole owner access']);
        $ownerRole->permissions()->sync(array_values($all));

        $otherRoleIds=Role::whereKeyNot($ownerRole->id)->pluck('id');
        if ($otherRoleIds->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::table('cs_user_roles')->whereIn('role_id',$otherRoleIds)->delete();
            \Illuminate\Support\Facades\DB::table('cs_role_permissions')->whereIn('role_id',$otherRoleIds)->delete();
            Role::whereIn('id',$otherRoleIds)->delete();
        }
        $this->command?->info('Owner permission catalog installed for the single administrator.');
    }
}
