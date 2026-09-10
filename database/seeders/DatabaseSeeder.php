<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = DB::table('users')->where('role', 'admin')->where('is_active', 1)->orderBy('id')->first()
            ?? DB::table('users')->where('is_active', 1)->orderBy('id')->first();

        if (!$owner) {
            throw new RuntimeException('An existing active owner account is required; the seeder never publishes a default password.');
        }

        $this->keepOnlyOwner((int) $owner->id);
        $this->seedSettings();
        $this->seedTailoring((int) $owner->id);
        $this->seedTimeline((int) $owner->id, (string) $owner->name);
        $this->seedClothStore((int) $owner->id, (string) $owner->name);
        $this->call(ProductionPermissionsSeeder::class);
        $ownerRole=DB::table('cs_roles')->where('name','Super Admin')->value('id');
        DB::table('cs_user_roles')->updateOrInsert(['user_id'=>(int)$owner->id],['role_id'=>$ownerRole]);
        Setting::flushCache();
    }

    private function keepOnlyOwner(int $ownerId): void
    {
        DB::table('users')->where('id', $ownerId)->update([
            'role' => 'admin', 'is_active' => 1, 'updated_at' => now(),
        ]);

        foreach ([
            ['orders', 'created_by'], ['payments', 'recorded_by'], ['expenses', 'created_by'],
            ['measurements', 'created_by'], ['activity_logs', 'user_id'], ['order_status_histories', 'user_id'],
            ['staff_payments', 'recorded_by'], ['staff_payments', 'reversed_by'],
            ['cs_stock_transactions', 'user_id'],
        ] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->whereNotNull($column)->where($column, '<>', $ownerId)->update([$column => $ownerId]);
            }
        }

        if (Schema::hasColumn('orders', 'tailor_id')) DB::table('orders')->update(['tailor_id' => null]);
        if (Schema::hasColumn('staff', 'user_id')) DB::table('staff')->update(['user_id' => null]);
        if (Schema::hasTable('cs_user_roles')) DB::table('cs_user_roles')->where('user_id', '<>', $ownerId)->delete();
        DB::table('users')->where('id', '<>', $ownerId)->delete();

        if (Schema::hasTable('cs_user_roles') && Schema::hasTable('cs_roles')) {
            $role = DB::table('cs_roles')->where('name', 'Super Admin')->value('id');
            if ($role) DB::table('cs_user_roles')->updateOrInsert(['user_id' => $ownerId], ['role_id' => $role]);
        }
    }

    private function seedSettings(): void
    {
        $values = [
            'currency' => 'Rs', 'timezone' => 'Asia/Karachi', 'order_prefix' => 'ORD-',
            'invoice_prefix' => 'INV-', 'allow_partial' => '1', 'low_stock_alert' => '10',
            'receipt_footer' => 'Thank you for choosing us. Please bring this receipt when collecting your order.',
        ];
        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], [
                'group' => 'general', 'value' => $value, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    private function seedTailoring(int $ownerId): void
    {
        $tailors = DB::table('staff')->orderBy('id')->get();
        if ($tailors->count() < 10) {
            $names = ['Muhammad Aslam','Rashid Mehmood','Imran Qureshi','Nadeem Akhtar','Shahid Hussain',
                'Adeel Ahmed','Waqas Ali','Tariq Bashir','Usman Raza','Kamran Yousaf'];
            foreach ($names as $i => $name) {
                if (DB::table('staff')->count() >= 10) break;
                DB::table('staff')->insert([
                    'name'=>$name,'phone'=>'0301'.str_pad((string)(4100000+$i),7,'0',STR_PAD_LEFT),
                    'address'=>'Karachi','joining_date'=>now()->subMonths(18-$i)->toDateString(),
                    'role'=>$i===0?'Master Tailor':'Tailor','salary_type'=>'Per Suit',
                    'monthly_salary'=>0,'per_suit_rate'=>1200+($i*75),'is_active'=>1,
                    'notes'=>'Experienced tailor','created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            $tailors = DB::table('staff')->orderBy('id')->get();
        }
        DB::table('staff')->whereNotIn('role', ['Master Tailor','Tailor'])->update(['role'=>'Tailor']);
        DB::table('staff')->whereRaw('LOWER(TRIM(name)) = ?', ['test'])->update([
            'name'=>'Irfan Malik','notes'=>'Experienced tailor','updated_at'=>now(),
        ]);
        DB::table('measurements')->whereRaw('LOWER(TRIM(tailor)) = ?', ['test'])->update(['tailor'=>'Irfan Malik']);
        $tailors = DB::table('staff')->orderBy('id')->get();

        // Older orders used login accounts as tailors. Keep every order and
        // attach any unassigned row to the preserved tailor directory.
        foreach (DB::table('orders')->whereNull('staff_id')->orderBy('id')->pluck('id') as $i => $orderId) {
            DB::table('orders')->where('id',$orderId)->update(['staff_id'=>$tailors[$i % $tailors->count()]->id]);
        }

        $services = [
            ['Shalwar Kameez Stitching','Men',2500],['Ladies Suit Stitching','Ladies',3200],
            ['Waistcoat Stitching','Men',4500],['Kurta Pajama Stitching','Men',2800],
            ['Three Piece Suit Stitching','Formal',15000],['Sherwani Stitching','Formal',18000],
            ['Trouser Stitching','Men',1800],['School Uniform Stitching','Uniform',2200],
            ['Bridal Dress Stitching','Ladies',22000],['Alteration and Fitting','Alteration',900],
        ];
        $serviceIds = [];
        foreach ($services as $i => [$name,$category,$price]) {
            $seededService = \App\Services\CatalogueIdentity::seed($name, ['sku'=>'TAIL-SVC-'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT),
                'name'=>$name,'category'=>$category,'type'=>'Service','price'=>$price,'cost_price'=>null,
                'stock_quantity'=>null,'low_stock_threshold'=>0,'unit'=>'piece','duration_days'=>5+$i,
                'status'=>'Active','description'=>'Professional '.$name,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        $serviceIds = array_map(fn($service) => \App\Models\ProductService::where('normalized_name', \App\Services\CatalogueIdentity::normalize($service[0]))->value('id') ?? \App\Models\ProductService::where('name',$service[0])->orderBy('id')->value('id'), $services);

        $customers = [
            ['Faisal Khan','03001234567','Gulshan-e-Iqbal'],['Saad Ahmed','03012345678','North Nazimabad'],
            ['Hassan Raza','03023456789','Clifton'],['Bilal Siddiqui','03034567890','PECHS'],
            ['Ayesha Malik','03045678901','DHA'],['Mariam Shah','03056789012','Bahadurabad'],
            ['Omar Farooq','03067890123','Federal B Area'],['Zainab Ali','03078901234','Tariq Road'],
            ['Hamza Iqbal','03089012345','Saddar'],['Sana Javed','03090123456','Korangi'],
        ];
        $customerIds=[];
        foreach ($customers as $i => [$name,$phone,$city]) {
            DB::table('customers')->updateOrInsert(['phone'=>$phone], [
                'code'=>'CUS-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),'name'=>$name,'city'=>$city,
                'address'=>$city.', Karachi','type'=>$i%4===0?'VIP':'Regular','behavior'=>'Reliable',
                'loyalty_score'=>4.5,'last_visit_at'=>now()->subDays($i),'is_active'=>1,
                'created_at'=>now()->subMonths(6),'updated_at'=>now(),
            ]);
            $customerIds[] = DB::table('customers')->where('phone',$phone)->value('id');
        }

        $statuses=['Delivered','Delivered','Ready','Ready for Verification','In Progress','Delivered','Ready','Ready','Delivered','Ready'];
        foreach (range(0,9) as $i) {
            $number='SEED-ORD-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT);
            if (DB::table('orders')->where('order_number',$number)->exists()) continue;
            $tailor=$tailors[$i % $tailors->count()];
            $customerId=$customerIds[$i];
            $total=(float) $services[$i][2] * (($i%3)+1);
            $advance=round($total * ([0.5,0.75,1][$i%3]),2);
            $created=now()->subDays(35-($i*3));
            $completed=$i<5 ? now()->subDays(4-$i) : now()->subDays(22-($i*2));
            DB::table('orders')->updateOrInsert(['order_number'=>$number], [
                'customer_id'=>$customerId,'product_service_id'=>$serviceIds[$i],'tailor_id'=>null,'staff_id'=>$tailor->id,
                'created_by'=>$ownerId,'invoice_number'=>'SEED-INV-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),
                'garment'=>$services[$i][0],'fabric'=>['Wash & Wear','Cotton','Lawn','Khaddar','Boski'][$i%5],
                'items'=>json_encode([['name'=>$services[$i][0],'qty'=>($i%3)+1,'price'=>$services[$i][2]]]),
                'total'=>$total,'advance'=>$advance,'balance'=>$total-$advance,'status'=>$statuses[$i],
                'priority'=>$i%4===0?'High':'Normal','progress'=>in_array($statuses[$i],['Delivered','Ready'])?100:65,
                'delivery_date'=>$created->copy()->addDays(10),'time_slot'=>'4:00 PM - 5:00 PM',
                'completed_at'=>$completed,'delivered_at'=>$statuses[$i]==='Delivered'?$completed->copy()->addDay():null,
                'created_at'=>$created,'updated_at'=>now(),
            ]);
            $order=DB::table('orders')->where('order_number',$number)->first();
            DB::table('measurements')->updateOrInsert(['order_id'=>$order->id,'piece_no'=>1], [
                'customer_id'=>$customerId,'created_by'=>$ownerId,'garment_type'=>$services[$i][0],
                'tailor'=>$tailor->name,'unit'=>'in','length'=>40+$i%4,'shoulder_width'=>17+$i%3,
                'sleeve_length'=>23+$i%3,'chest'=>38+$i,'waist'=>34+$i,'hip'=>39+$i,'collar'=>15+$i/4,
                'details'=>json_encode(['fit'=>'Regular','cuff'=>'Button']), 'created_at'=>$created,'updated_at'=>now(),
            ]);
            $measurementId=DB::table('measurements')->where('order_id',$order->id)->value('id');
            DB::table('orders')->where('id',$order->id)->update(['measurement_id'=>$measurementId]);
            if ($statuses[$i] !== 'In Progress') {
                DB::table('staff_work_logs')->updateOrInsert(['order_id'=>$order->id], [
                    'staff_id'=>$tailor->id,'garment'=>$services[$i][0],'quantity'=>($i%3)+1,
                    'rate'=>$tailor->per_suit_rate,'amount'=>$tailor->per_suit_rate*(($i%3)+1),
                    'completed_on'=>$completed->toDateString(),'notes'=>'Completed against '.$number,
                    'created_at'=>$completed,'updated_at'=>now(),
                ]);
            } else {
                DB::table('staff_work_logs')->where('order_id',$order->id)->delete();
                DB::table('orders')->where('id',$order->id)->update(['completed_at'=>null]);
            }
            DB::table('payments')->updateOrInsert(['operation_key'=>'seed-tail-payment-'.($i+1)], [
                'invoice_id'=>$order->invoice_number,'order_id'=>$order->id,'customer_id'=>$customerId,
                'amount'=>$advance,'type'=>'Advance','status'=>'Completed','payment_method'=>['Cash','Bank Transfer','Card'][$i%3],
                'recorded_by'=>$ownerId,'date'=>$created,'created_at'=>$created,'updated_at'=>now(),
            ]);
            DB::table('deliveries')->updateOrInsert(['order_id'=>$order->id], [
                'status'=>$statuses[$i]==='Delivered'?'Delivered':($statuses[$i]==='Ready'?'Ready':'Scheduled'),
                'recipient_name'=>$customers[$i][0],'address'=>$customers[$i][2].', Karachi',
                'delivery_date'=>$order->delivery_date,'delivered_at'=>$order->delivered_at,
                'notes'=>'Collection from the shop','created_at'=>$created,'updated_at'=>now(),
            ]);
        }

        // Backfill a stitching entry for every preserved order whose garment
        // was already finished, without altering existing work-log history.
        $finished=DB::table('orders')->whereIn('status',['Ready','Ready for Verification','Delivered','Completed'])
            ->whereNotNull('staff_id')->orderBy('id')->get();
        foreach($finished as $order) {
            if(DB::table('staff_work_logs')->where('order_id',$order->id)->exists()) continue;
            $tailor=$tailors->firstWhere('id',$order->staff_id);
            if(!$tailor) continue;
            $done=Carbon::parse($order->completed_at ?? $order->updated_at ?? $order->created_at)->toDateString();
            DB::table('staff_work_logs')->insert(['staff_id'=>$tailor->id,'order_id'=>$order->id,
                'garment'=>$order->garment ?: 'Tailored garment','quantity'=>1,'rate'=>$tailor->per_suit_rate,
                'amount'=>$tailor->per_suit_rate,'completed_on'=>$done,'notes'=>'Preserved completed-order history',
                'created_at'=>now(),'updated_at'=>now()]);
        }

        $tailorExpenses=[
            ['Shop Rent',65000,'Rent'],['Electricity Bill',18500,'Utilities'],['Tailor Wages',92000,'Salary'],
            ['Sewing Machine Service',8500,'Maintenance'],['Threads and Buttons',12000,'Raw Material'],
            ['Pressing Supplies',4500,'Supplies'],['Packaging Bags',6200,'Supplies'],['Internet Bill',4500,'Utilities'],
            ['Shop Cleaning',7000,'Maintenance'],['Market Cloth Transport',5500,'Transport'],
        ];
        foreach($tailorExpenses as $i=>[$description,$amount,$category]) DB::table('expenses')->updateOrInsert(
            ['reference'=>'SEED-EXP-'.($i+1)],['description'=>$description,'amount'=>$amount,'category'=>$category,
            'payment_method'=>$i%3===0?'Bank':'Cash','created_by'=>$ownerId,'date'=>now()->subDays($i*3),
            'created_at'=>now(),'updated_at'=>now()]);
    }

    private function seedClothStore(int $ownerId, string $ownerName): void
    {
        $tables=['cs_adjustment_lines','cs_financial_adjustments','cs_payment_allocations','cs_inventory_allocations',
            'cs_return_items','cs_returns','cs_customer_ledgers','cs_customer_payments','cs_loyalty_transactions',
            'cs_order_items','cs_orders','cs_customers','cs_stock_transactions','cs_product_locations','cs_products','cs_categories',
            'cs_expenses','cs_discounts','cs_activity_logs'];
        Schema::disableForeignKeyConstraints();
        foreach($tables as $table) if(Schema::hasTable($table)) DB::table($table)->truncate();
        if(Schema::hasTable('cs_locations')) DB::table('cs_locations')->truncate();
        Schema::enableForeignKeyConstraints();

        $locationId=DB::table('cs_locations')->insertGetId(['name'=>'Main Store','type'=>'Store',
            'address'=>'Main shop','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $categories=[
            ['Wash & Wear','fa-shirt','#EEF2FF','#4338CA'],['Cotton','fa-seedling','#ECFDF5','#047857'],
            ['Lawn','fa-leaf','#FDF2F8','#BE185D'],['Khaddar','fa-layer-group','#FFF7ED','#C2410C'],
            ['Boski','fa-gem','#FEFCE8','#A16207'],
        ];
        $categoryIds=[];
        foreach($categories as [$name,$icon,$bg,$text]) $categoryIds[]=DB::table('cs_categories')->insertGetId([
            'name'=>$name,'icon'=>$icon,'color_bg'=>$bg,'color_text'=>$text,'description'=>$name.' fabrics',
            'created_at'=>now(),'updated_at'=>now()]);
        $products=[
            ['Royal Blue Wash & Wear',1450,980,42,'metre'],['Charcoal Wash & Wear',1550,1050,36,'metre'],
            ['Premium White Cotton',950,620,55,'metre'],['Sky Blue Cotton',1050,690,48,'metre'],
            ['Summer Floral Lawn',1250,800,30,'metre'],['Embroidered Lawn',2100,1450,24,'metre'],
            ['Brown Winter Khaddar',1750,1180,32,'metre'],['Olive Khaddar',1650,1120,28,'metre'],
            ['Cream Boski',2600,1900,22,'metre'],['Golden Boski',2850,2050,18,'metre'],
        ];
        $productIds=[];
        foreach($products as $i=>[$name,$price,$cost,$opening,$unit]) {
            $productIds[]=DB::table('cs_products')->insertGetId(['cs_category_id'=>$categoryIds[intdiv($i,2)],
                'name'=>$name,'sku'=>'FAB-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),'barcode'=>'220000000'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),
                'price'=>$price,'cost_price'=>$cost,'stock_quantity'=>$opening,'reserved_quantity'=>0,'incoming_quantity'=>0,
                'low_stock_threshold'=>10,'suggested_reorder_qty'=>30,'ignore_stock_alerts'=>0,'unit'=>$unit,
                'status'=>'Active','description'=>'Quality fabric purchased directly from the market',
                'created_at'=>now()->subMonths(2),'updated_at'=>now()]);
            DB::table('cs_product_locations')->insert(['cs_product_id'=>$productIds[$i],'cs_location_id'=>$locationId,
                'quantity'=>$opening,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('cs_stock_transactions')->insert(['cs_product_id'=>$productIds[$i],'type'=>'in','quantity'=>$opening,
                'reason'=>'Market stock purchase','previous_qty'=>0,'new_qty'=>$opening,'reference'=>'MARKET-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),
                'notes'=>'Opening stock bought directly by owner','user_id'=>$ownerId,'to_location_id'=>$locationId,
                'created_at'=>now()->subMonths(2),'updated_at'=>now()]);
        }
        $clothCustomers=[
            ['Ali Raza','03111234567','Karachi'],['Noman Sheikh','03122345678','Karachi'],['Hina Ahmed','03133456789','Karachi'],
            ['Sohail Khan','03144567890','Karachi'],['Rabia Noor','03155678901','Karachi'],['Danish Iqbal','03166789012','Karachi'],
            ['Mehwish Tariq','03177890123','Karachi'],['Arsalan Baig','03188901234','Karachi'],['Saba Qureshi','03199012345','Karachi'],
            ['Farhan Siddiqui','03210123456','Karachi'],
        ];
        $clothCustomerIds=[];
        foreach($clothCustomers as $i=>[$name,$phone,$city]) $clothCustomerIds[]=DB::table('cs_customers')->insertGetId([
            'name'=>$name,'phone'=>$phone,'city'=>$city,'notes'=>'Regular shop customer','due_balance'=>0,'total_purchases'=>0,
            'last_purchase_date'=>now()->subDays($i*2),'customer_level'=>$i<3?'Gold':'Regular','loyalty_points'=>20+$i*5,
            'created_at'=>now()->subMonths(5),'updated_at'=>now()]);

        foreach(range(0,9) as $i) {
            $qty=1.5+($i%3)*0.5; $product=$products[$i]; $subtotal=$qty*$product[1]; $paid=$i%4===0?$subtotal-500:$subtotal;
            $orderId=DB::table('cs_orders')->insertGetId(['invoice_number'=>'CS-INV-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),
                'cs_customer_id'=>$clothCustomerIds[$i],'subtotal'=>$subtotal,'discount'=>0,'total_amount'=>$subtotal,
                'gross_profit'=>($product[1]-$product[2])*$qty,'total_meters_sold'=>$qty,'paid_amount'=>$paid,
                'payment_method'=>['Cash','Bank Transfer','Card'][$i%3],'status'=>'Completed','remaining_amount'=>$subtotal-$paid,
                'refund_due'=>0,'payment_status'=>$paid==$subtotal?'Paid':'Partial',
                'created_at'=>now()->subDays(28-$i*2),'updated_at'=>now()]);
            $itemId=DB::table('cs_order_items')->insertGetId(['cs_order_id'=>$orderId,'cs_product_id'=>$productIds[$i],
                'quantity'=>$qty,'unit_price'=>$product[1],'unit_cost'=>$product[2],'total'=>$subtotal,
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('cs_inventory_allocations')->insert(['order_item_id'=>$itemId,'location_id'=>$locationId,
                'quantity'=>$qty,'restored_quantity'=>0,'created_at'=>now(),'updated_at'=>now()]);
            $remaining=$product[3]-$qty;
            DB::table('cs_product_locations')->where('cs_product_id',$productIds[$i])->update(['quantity'=>$remaining]);
            DB::table('cs_products')->where('id',$productIds[$i])->update(['stock_quantity'=>$remaining]);
            DB::table('cs_stock_transactions')->insert(['cs_product_id'=>$productIds[$i],'type'=>'out','quantity'=>$qty,
                'reason'=>'Sale','previous_qty'=>$product[3],'new_qty'=>$remaining,'reference'=>'CS-INV-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),
                'user_id'=>$ownerId,'from_location_id'=>$locationId,'created_at'=>now()->subDays(28-$i*2),'updated_at'=>now()]);
            $paymentId=DB::table('cs_customer_payments')->insertGetId(['cs_customer_id'=>$clothCustomerIds[$i],
                'cs_order_id'=>$orderId,'amount'=>$paid,'payment_type'=>'Sale Payment','payment_method'=>['Cash','Bank Transfer','Card'][$i%3],
                'reference'=>'CS-PAY-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),'payment_date'=>now()->subDays(28-$i*2)->toDateString(),
                'received_by'=>$ownerName,'status'=>'Completed','operation_key'=>'seed-cloth-payment-'.($i+1),
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('cs_payment_allocations')->insert(['payment_id'=>$paymentId,'order_id'=>$orderId,'amount'=>$paid,
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('cs_customer_ledgers')->insert(['cs_customer_id'=>$clothCustomerIds[$i],'type'=>'Sale',
                'reference'=>'CS-INV-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),'debit'=>$subtotal,'credit'=>$paid,
                'balance'=>$subtotal-$paid,'description'=>'Fabric sale','date'=>now()->subDays(28-$i*2)->toDateString(),
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('cs_customers')->where('id',$clothCustomerIds[$i])->update([
                'due_balance'=>$subtotal-$paid,'total_purchases'=>$subtotal]);
        }
        $clothExpenses=[
            ['Market Transport',3500,'Transport'],['Shopping Bags',4200,'Packaging'],['Counter Rolls',1800,'Supplies'],
            ['Card Machine Charges',2600,'Bank Charges'],['Cloth Rack Repair',6500,'Maintenance'],['Shop Electricity',12500,'Utilities'],
            ['Store Cleaning',5000,'Maintenance'],['Fabric Samples',8000,'Marketing'],['Local Delivery',3200,'Transport'],
            ['Internet Service',4500,'Utilities'],
        ];
        foreach($clothExpenses as $i=>[$description,$amount,$category]) DB::table('cs_expenses')->insert([
            'category'=>$category,'description'=>$description,'amount'=>$amount,'payment_method'=>$i%3===0?'Bank Transfer':'Cash',
            'paid_by'=>$ownerName,'reference'=>'CS-EXP-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),
            'status'=>'Approved','created_by'=>$ownerName,'expense_date'=>now()->subDays($i*3)->toDateString(),
            'created_at'=>now(),'updated_at'=>now()]);
        foreach(range(1,10) as $i) DB::table('cs_discounts')->insert([
            'name'=>$i<=5?'Seasonal Offer '.$i:'Loyal Customer '.$i,'code'=>'SAVE'.($i*5),
            'type'=>$i%2?'Percentage':'Fixed','value'=>$i%2?5+$i:200+$i*25,
            'start_date'=>now()->subDays(5),'end_date'=>now()->addMonths(2),'min_purchase'=>1000,
            'max_discount'=>1000,'usage_limit'=>100,'customer_limit'=>2,'usage_count'=>$i-1,
            'is_active'=>$i<=6,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function seedTimeline(int $ownerId, string $ownerName): void
    {
        // Notifications and activity entries are transient dashboard data. Clear
        // stale demo alerts without touching orders, measurements or stitching
        // history, then rebuild a concise timeline from the connected seed orders.
        DB::table('notifications')->delete();
        DB::table('activity_logs')->delete();

        $orders = DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.order_number', 'like', 'SEED-ORD-%')
            ->orderBy('orders.order_number')
            ->limit(10)
            ->get([
                'orders.id', 'orders.order_number', 'orders.customer_id',
                'orders.status', 'orders.total', 'customers.name as customer_name',
            ]);

        foreach ($orders as $index => $order) {
            $createdAt = now()->subHours(2 + ($index * 3));
            $isRead = $index >= 6;
            $message = match ($order->status) {
                'Delivered' => "{$order->order_number} for {$order->customer_name} was delivered successfully.",
                'Ready', 'Ready for Verification' => "{$order->order_number} for {$order->customer_name} is ready for collection.",
                default => "Stitching is in progress for {$order->order_number} ({$order->customer_name}).",
            };

            DB::table('notifications')->insert([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'title' => $order->status === 'Delivered' ? 'Order Delivered' : 'Order Update',
                'message' => $message,
                'type' => 'Info',
                'category' => 'orders',
                'icon' => $order->status === 'Delivered' ? 'fa-check-circle' : 'fa-scissors',
                'color' => $order->status === 'Delivered' ? 'success' : 'primary',
                'action_url' => '/orders/'.$order->id,
                'is_read' => $isRead,
                'read_at' => $isRead ? $createdAt->copy()->addHour() : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            DB::table('activity_logs')->insert([
                'action' => 'Order '.$order->status,
                'category' => 'orders',
                'event' => 'order.status',
                'description' => "{$order->order_number} for {$order->customer_name} is {$order->status}.",
                'subject_type' => 'App\\Models\\Order',
                'subject_id' => $order->id,
                'properties' => json_encode(['status' => $order->status, 'total' => $order->total]),
                'user_id' => $ownerId,
                'actor_name' => $ownerName,
                'ip_address' => '127.0.0.1',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
