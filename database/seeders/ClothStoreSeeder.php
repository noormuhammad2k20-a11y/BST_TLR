<?php

namespace Database\Seeders;

use App\Models\ClothStore\Category;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\Supplier;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\OrderItem;
use App\Models\ClothStore\Expense;
use App\Models\ClothStore\Purchase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ClothStoreSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categories = [
            ['name' => 'Unstitched Suits', 'icon' => 'fa-box-open', 'color_bg' => 'bg-indigo-50', 'color_text' => 'text-indigo-600'],
            ['name' => 'Stitched Kurta', 'icon' => 'fa-shirt', 'color_bg' => 'bg-emerald-50', 'color_text' => 'text-emerald-600'],
            ['name' => 'Bridal Wear', 'icon' => 'fa-person-dress', 'color_bg' => 'bg-pink-50', 'color_text' => 'text-pink-600'],
            ['name' => 'Fabric by Meter', 'icon' => 'fa-scroll', 'color_bg' => 'bg-amber-50', 'color_text' => 'text-amber-600'],
        ];

        $catModels = [];
        foreach ($categories as $c) {
            $catModels[$c['name']] = Category::create($c);
        }

        // 2. Products
        $productsData = [
            ['cs_category_id' => $catModels['Unstitched Suits']->id, 'name' => 'Gul Ahmed Premium Lawn', 'price' => 6500, 'cost_price' => 4500, 'stock_quantity' => 25, 'unit' => 'suit'],
            ['cs_category_id' => $catModels['Unstitched Suits']->id, 'name' => 'Alkaram Winter Khaddar', 'price' => 4200, 'cost_price' => 3000, 'stock_quantity' => 5, 'unit' => 'suit', 'low_stock_threshold' => 10], // Low stock
            ['cs_category_id' => $catModels['Stitched Kurta']->id, 'name' => 'J. Classic Men Kurta', 'price' => 3500, 'cost_price' => 2200, 'stock_quantity' => 15, 'unit' => 'pcs'],
            ['cs_category_id' => $catModels['Fabric by Meter']->id, 'name' => 'Pure Raw Silk', 'price' => 1800, 'cost_price' => 1200, 'stock_quantity' => 150, 'unit' => 'meter'],
            ['cs_category_id' => $catModels['Fabric by Meter']->id, 'name' => 'Imported Chiffon', 'price' => 900, 'cost_price' => 500, 'stock_quantity' => 8, 'unit' => 'meter', 'low_stock_threshold' => 20], // Low stock
        ];

        $products = [];
        foreach ($productsData as $pd) {
            $products[] = Product::create($pd);
        }

        // 3. Customers
        $customers = [
            Customer::create(['name' => 'Ali Raza', 'phone' => '03001234567', 'due_balance' => 5000]),
            Customer::create(['name' => 'Fatima Noor', 'phone' => '03119876543', 'due_balance' => 0]),
            Customer::create(['name' => 'Zainab Tariq', 'phone' => '03334567890', 'due_balance' => 12000]),
            Customer::create(['name' => 'Kamran Khan', 'phone' => '03215678901', 'due_balance' => 2500]),
        ];

        // 4. Suppliers
        $suppliers = [
            Supplier::create(['name' => 'Gul Ahmed Wholesale', 'due_balance' => 45000]),
            Supplier::create(['name' => 'Sitara Fabrics FSD', 'due_balance' => 120000]),
        ];

        // 5. Orders (Past 30 days)
        for ($i = 0; $i < 40; $i++) {
            // Generate random date within last 30 days
            $date = Carbon::now()->subDays(rand(0, 30))->subHours(rand(1, 23));
            
            $customer = $customers[array_rand($customers)];
            $prod = $products[array_rand($products)];
            
            $qty = rand(1, 10);
            $subtotal = $prod->price * $qty;
            $discount = rand(0, 1) ? ($subtotal * 0.1) : 0;
            $total = $subtotal - $discount;
            $cost = $prod->cost_price * $qty;
            $profit = $total - $cost;

            $metersSold = $prod->unit === 'meter' ? $qty : 0;

            $order = Order::create([
                'invoice_number' => 'INV-'.str_pad($i + 1000, 4, '0', STR_PAD_LEFT),
                'cs_customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $total,
                'gross_profit' => $profit,
                'total_meters_sold' => $metersSold,
                'paid_amount' => $total,
                'payment_method' => ['Cash', 'Card'][rand(0, 1)],
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            OrderItem::create([
                'cs_order_id' => $order->id,
                'cs_product_id' => $prod->id,
                'quantity' => $qty,
                'unit_price' => $prod->price,
                'unit_cost' => $prod->cost_price,
                'total' => $total,
            ]);

            // Accumulate customer purchases
            $customer->total_purchases += $total;
            $customer->last_purchase_date = max($customer->last_purchase_date, $date);
            $customer->save();
        }

        // Add some orders specifically for "Today" so the dashboard is alive
        for ($i = 0; $i < 5; $i++) {
            $customer = $customers[array_rand($customers)];
            $prod = $products[array_rand($products)];
            
            $qty = rand(2, 5);
            $total = $prod->price * $qty;
            $profit = $total - ($prod->cost_price * $qty);

            $order = Order::create([
                'invoice_number' => 'INV-TODAY-'.$i,
                'cs_customer_id' => $customer->id,
                'subtotal' => $total,
                'discount' => 0,
                'total_amount' => $total,
                'gross_profit' => $profit,
                'total_meters_sold' => $prod->unit === 'meter' ? $qty : 0,
                'paid_amount' => $total,
                'payment_method' => 'Cash',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            OrderItem::create([
                'cs_order_id' => $order->id,
                'cs_product_id' => $prod->id,
                'quantity' => $qty,
                'unit_price' => $prod->price,
                'unit_cost' => $prod->cost_price,
                'total' => $total,
            ]);
        }

        // 6. Expenses
        $expenseCategories = ['Utility Bill', 'Staff Salary', 'Tea/Snacks', 'Maintenance'];
        for ($i = 0; $i < 10; $i++) {
            Expense::create([
                'category' => $expenseCategories[array_rand($expenseCategories)],
                'description' => 'Routine expense',
                'amount' => rand(500, 5000),
                'expense_date' => Carbon::now()->subDays(rand(0, 15)),
            ]);
        }
        
        // One expense today
        Expense::create([
            'category' => 'Tea/Snacks',
            'description' => 'Evening tea',
            'amount' => 350,
            'expense_date' => Carbon::now(),
        ]);
    }
}
