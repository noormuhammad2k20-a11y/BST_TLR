<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cs_locations')) return;

        $mainId=DB::table('cs_locations')->where('name','Main Store')->orderBy('id')->value('id');
        if (!$mainId) {
            $mainId=DB::table('cs_locations')->insertGetId([
                'name'=>'Main Store','type'=>'Store','address'=>'Main shop','is_active'=>true,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        if (Schema::hasTable('cs_inventory_allocations')) {
            DB::table('cs_inventory_allocations')->where('location_id','<>',$mainId)->update(['location_id'=>$mainId]);
        }
        if (Schema::hasTable('cs_stock_transactions')) {
            DB::table('cs_stock_transactions')->whereNotNull('from_location_id')->update(['from_location_id'=>$mainId]);
            DB::table('cs_stock_transactions')->whereNotNull('to_location_id')->update(['to_location_id'=>$mainId]);
        }

        if (Schema::hasTable('cs_products') && Schema::hasTable('cs_product_locations')) {
            foreach(DB::table('cs_products')->orderBy('id')->pluck('id') as $productId) {
                $quantity=(string)DB::table('cs_product_locations')->where('cs_product_id',$productId)->sum('quantity');
                DB::table('cs_product_locations')->updateOrInsert(
                    ['cs_product_id'=>$productId,'cs_location_id'=>$mainId],
                    ['quantity'=>$quantity,'created_at'=>now(),'updated_at'=>now()]
                );
                DB::table('cs_product_locations')->where('cs_product_id',$productId)->where('cs_location_id','<>',$mainId)->delete();
                DB::table('cs_products')->where('id',$productId)->update(['stock_quantity'=>$quantity]);
            }
        }

        DB::table('cs_locations')->where('id','<>',$mainId)->delete();
        DB::table('cs_locations')->where('id',$mainId)->update([
            'name'=>'Main Store','type'=>'Store','is_active'=>true,'updated_at'=>now(),
        ]);

        if (Schema::hasTable('cs_stock_transactions') && in_array(DB::getDriverName(),['mysql','mariadb'],true)) {
            DB::statement("ALTER TABLE cs_stock_transactions MODIFY COLUMN type ENUM('in','out','adjustment') NOT NULL");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only: this installation has one owner-operated shop.');
    }
};
