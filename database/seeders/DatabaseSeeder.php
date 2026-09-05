<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Measurement;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $users     = $this->seedUsers();
        $services  = $this->seedServices();
        $customers = $this->seedCustomers();

        $this->seedMeasurements($customers, $users);
        $orders = $this->seedOrders($customers, $services, $users);

        $this->seedExpenses($users['admin']);
        $this->seedNotifications($orders);
        $this->seedActivity($orders, $users['admin']);

        Setting::flushCache();
    }

    private function seedSettings(): void
    {
        $settings = [
            'store_name'           => 'Atelier Tailor House',
            'currency'             => '₹',
            'tax_rate'             => '18',
            'address'              => 'Shop #5, Main Market, Mumbai',
            'phone'                => '+91 98765 43210',
            'email'                => 'contact@atelier.com',
            'website'              => 'www.atelier.com',
            'receipt_footer'       => 'Thank you for choosing Atelier. Please bring this receipt for collection.',
            'invoice_terms'        => '50% advance required for all custom tailoring. Full payment due upon collection.',
            'whatsapp_enabled'     => '1',
            'email_enabled'        => '1',
            'sms_enabled'          => '0',
            'order_prefix'         => 'ORD-',
            'invoice_prefix'       => 'INV-',
            'allow_partial'        => '1',
            'low_stock_alert'      => '10',
            'auto_delivery_update' => '1',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['group' => 'general', 'value' => $value]);
        }
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@ateliercraft.com'],
            [
                'name'         => 'Noor M Hingorjo',
                'display_name' => 'Noor',
                'password'     => Hash::make('password'),
                'role'         => User::ROLE_ADMIN,
                'title'        => 'Master Tailor · Administrator',
                'phone'        => '+91 98765 43210',
                'badge'        => 'Founder',
                'is_active'    => true,
            ]
        );

        $staff = User::updateOrCreate(
            ['email' => 'staff@ateliercraft.com'],
            [
                'name'      => 'Priya Sharma',
                'password'  => Hash::make('password'),
                'role'      => User::ROLE_STAFF,
                'title'     => 'Front Desk',
                'is_active' => true,
            ]
        );

        $tailors = [];
        foreach ([['Ahmed', 'ahmed'], ['Bilal', 'bilal'], ['Sana', 'sana'], ['Vikram', 'vikram']] as [$name, $handle]) {
            $tailors[] = User::updateOrCreate(
                ['email' => "{$handle}@ateliercraft.com"],
                [
                    'name'      => $name,
                    'password'  => Hash::make('password'),
                    'role'      => User::ROLE_TAILOR,
                    'title'     => 'Tailor',
                    'is_active' => true,
                ]
            );
        }

        return ['admin' => $admin, 'staff' => $staff, 'tailors' => $tailors];
    }

    /**
     * @return array<string, ProductService>
     */
    private function seedServices(): array
    {
        $definitions = [
            ['name' => 'Shalwar Kameez Stitching', 'category' => 'Service', 'type' => 'Service', 'price' => 2500,  'duration_days' => 5,  'description' => 'Traditional shalwar kameez tailoring with standard finishing.'],
            ['name' => 'Premium Suit Stitching',   'category' => 'Suit',    'type' => 'Service', 'price' => 12000, 'duration_days' => 12, 'description' => 'Fully canvassed bespoke suit with two fittings included.'],
            ['name' => 'Sherwani Stitching',       'category' => 'Men',     'type' => 'Service', 'price' => 18000, 'duration_days' => 14, 'description' => 'Hand-finished sherwani with lining and detailing.'],
            ['name' => 'Saree Blouse Stitching',   'category' => 'Ladies',  'type' => 'Service', 'price' => 4000,  'duration_days' => 6,  'description' => 'Fitted blouse with custom neckline and sleeve options.'],
            ['name' => 'Italian Wool Navy',        'category' => 'Fabric',  'type' => 'Fabric',  'price' => 4500,  'sku' => 'FAB-001', 'stock_quantity' => 24, 'low_stock_threshold' => 10, 'unit' => 'metre', 'cost_price' => 3100, 'description' => 'Super 120s Italian wool, navy.'],
            ['name' => 'Signature Buttons Set',    'category' => 'Other',   'type' => 'Product', 'price' => 850,   'sku' => 'ACC-042', 'stock_quantity' => 8,  'low_stock_threshold' => 10, 'unit' => 'set', 'cost_price' => 420, 'description' => 'Horn button set of twelve.'],
        ];

        $services = [];
        foreach ($definitions as $definition) {
            $service = ProductService::updateOrCreate(
                ['name' => $definition['name']],
                array_merge(['status' => 'Active'], $definition)
            );
            $services[$definition['name']] = $service;
        }

        return $services;
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(): array
    {
        $definitions = [
            ['name' => 'Ahmad Ali',    'phone' => '0300-1234567',   'email' => 'ahmad@example.com', 'type' => 'Regular', 'city' => 'Mumbai'],
            ['name' => 'Rahul Mehta',  'phone' => '+91 98765 43210', 'type' => 'VIP',     'city' => 'Delhi'],
            ['name' => 'Zainab Abbas', 'phone' => '0300-9876543',   'type' => 'Regular', 'city' => 'Lahore'],
            ['name' => 'Ananya Iyer',  'phone' => '+91 99887 76655', 'type' => 'Premium', 'city' => 'Chennai'],
            ['name' => 'Rohan Gupta',  'phone' => '+91 90000 11122', 'type' => 'VIP',     'city' => 'Pune'],
            ['name' => 'Sara Ali',     'phone' => '+91 90000 33344', 'type' => 'Premium', 'city' => 'Hyderabad'],
        ];

        $customers = [];
        foreach ($definitions as $definition) {
            $customer = Customer::updateOrCreate(
                ['phone' => $definition['phone']],
                array_merge($definition, ['last_visit_at' => now()->subDays(random_int(0, 20))])
            );
            $customers[$definition['name']] = $customer;
        }

        return $customers;
    }

    /**
     * @param array<string, Customer> $customers
     * @param array<string, mixed>    $users
     */
    private function seedMeasurements(array $customers, array $users): void
    {
        $samples = [
            ['customer' => 'Ahmad Ali',   'garment_type' => 'Shalwar Kameez', 'length' => 42, 'shoulder_width' => 18, 'sleeve_length' => 24, 'chest' => 42, 'chest_losing' => 2, 'waist' => 40, 'waist_losing' => 2, 'hip' => 42, 'hip_losing' => 2, 'collar' => 16, 'ghera' => 24, 'salwar_length' => 40, 'pancho' => 14],
            ['customer' => 'Rahul Mehta', 'garment_type' => 'Premium Suit',   'length' => 31, 'shoulder_width' => 18, 'sleeve_length' => 25, 'chest' => 44, 'chest_losing' => 3, 'waist' => 38, 'waist_losing' => 2, 'hip' => 41, 'hip_losing' => 2, 'collar' => 16.5, 'armhole' => 21, 'elbow' => 14],
            ['customer' => 'Ananya Iyer', 'garment_type' => 'Saree Blouse',   'length' => 15, 'shoulder_width' => 14, 'sleeve_length' => 10, 'chest' => 36, 'chest_losing' => 1.5, 'waist' => 30, 'waist_losing' => 1.5, 'hip' => 38, 'hip_losing' => 1.5, 'collar' => 14],
        ];

        foreach ($samples as $index => $sample) {
            $customer = $customers[$sample['customer']] ?? null;
            if (!$customer) {
                continue;
            }

            unset($sample['customer']);

            Measurement::updateOrCreate(
                ['customer_id' => $customer->id, 'garment_type' => $sample['garment_type']],
                array_merge($sample, [
                    'tailor'     => $users['tailors'][$index % count($users['tailors'])]->name,
                    'unit'       => 'in',
                    'created_by' => $users['admin']->id,
                ])
            );
        }
    }

    /**
     * @param array<string, Customer>       $customers
     * @param array<string, ProductService> $services
     * @param array<string, mixed>          $users
     * @return array<int, Order>
     */
    private function seedOrders(array $customers, array $services, array $users): array
    {
        $definitions = [
            ['customer' => 'Ahmad Ali',    'service' => 'Premium Suit Stitching',   'garment' => '3-Piece Suit', 'fabric' => 'Italian Wool',  'total' => 18000, 'advance' => 5000,  'status' => 'In Progress', 'priority' => 'High',    'due' => 2],
            ['customer' => 'Rahul Mehta',  'service' => 'Sherwani Stitching',       'garment' => 'Sherwani',     'fabric' => 'Raw Silk',      'total' => 45000, 'advance' => 20000, 'status' => 'Pending',     'priority' => 'Express', 'due' => 7],
            ['customer' => 'Ananya Iyer',  'service' => 'Saree Blouse Stitching',   'garment' => 'Saree Blouse', 'fabric' => 'Silk',          'total' => 8000,  'advance' => 8000,  'status' => 'Delivered',   'priority' => 'Normal',  'due' => -1],
            ['customer' => 'Zainab Abbas', 'service' => 'Shalwar Kameez Stitching', 'garment' => 'Shalwar Kameez', 'fabric' => 'Lawn',        'total' => 2500,  'advance' => 1000,  'status' => 'Ready',       'priority' => 'Normal',  'due' => 0],
            ['customer' => 'Rohan Gupta',  'service' => 'Premium Suit Stitching',   'garment' => '2-Piece Suit', 'fabric' => 'Merino Wool',   'total' => 22000, 'advance' => 10000, 'status' => 'Ready for Verification', 'priority' => 'High', 'due' => 4],
            ['customer' => 'Sara Ali',     'service' => 'Saree Blouse Stitching',   'garment' => 'Designer Blouse', 'fabric' => 'Georgette',  'total' => 6500,  'advance' => 2000,  'status' => 'In Progress', 'priority' => 'Normal',  'due' => 5],
        ];

        $orders = [];

        foreach ($definitions as $index => $definition) {
            $customer = $customers[$definition['customer']];
            $service  = $services[$definition['service']] ?? null;
            $tailor   = $users['tailors'][$index % count($users['tailors'])];

            $order = Order::updateOrCreate(
                ['order_number' => 'ORD-' . (1001 + $index)],
                [
                    'customer_id'        => $customer->id,
                    'product_service_id' => $service?->id,
                    'tailor_id'          => $tailor->id,
                    'created_by'         => $users['admin']->id,
                    'invoice_number'     => 'INV-' . (1001 + $index),
                    'garment'            => $definition['garment'],
                    'fabric'             => $definition['fabric'],
                    'items'              => [[
                        'name'   => $definition['garment'],
                        'fabric' => $definition['fabric'],
                        'qty'    => 1,
                        'price'  => $definition['total'],
                    ]],
                    'total'         => $definition['total'],
                    'advance'       => $definition['advance'],
                    'balance'       => max($definition['total'] - $definition['advance'], 0),
                    'status'        => $definition['status'],
                    'priority'      => $definition['priority'],
                    'progress'      => Order::progressFor($definition['status']),
                    'delivery_date' => Carbon::now()->addDays($definition['due'])->setTime(16, 0),
                    'time_slot'     => '3:00 PM - 4:00 PM',
                    'delivered_at'  => $definition['status'] === 'Delivered' ? Carbon::now()->subDay() : null,
                    'completed_at'  => in_array($definition['status'], ['Ready', 'Delivered'], true) ? Carbon::now()->subDays(2) : null,
                    'created_at'    => Carbon::now()->subDays(10 - $index),
                ]
            );

            if ($definition['advance'] > 0) {
                Payment::updateOrCreate(
                    ['order_id' => $order->id, 'type' => 'Advance'],
                    [
                        'invoice_id'     => $order->invoice_number,
                        'customer_id'    => $customer->id,
                        'amount'         => $definition['advance'],
                        'status'         => 'Completed',
                        'payment_method' => ['Cash', 'Card', 'UPI'][$index % 3],
                        'date'           => $order->created_at,
                        'recorded_by'    => $users['admin']->id,
                    ]
                );
            }

            OrderStatusHistory::firstOrCreate(
                ['order_id' => $order->id, 'to_status' => $definition['status']],
                [
                    'from_status' => null,
                    'label'       => 'Order created',
                    'actor_name'  => $users['admin']->name,
                    'user_id'     => $users['admin']->id,
                    'created_at'  => $order->created_at,
                ]
            );

            Delivery::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'status' => match ($definition['status']) {
                        'Ready'                  => 'Ready',
                        'Delivered', 'Completed' => 'Delivered',
                        default                  => 'Scheduled',
                    },
                    'address'        => $customer->city,
                    'delivery_date'  => $order->delivery_date,
                    'delivered_at'   => $definition['status'] === 'Delivered' ? Carbon::now()->subDay() : null,
                    'recipient_name' => $customer->name,
                    'courier_name'   => $index % 2 === 0 ? 'In-store Pickup' : null,
                ]
            );

            $orders[] = $order;
        }

        return $orders;
    }

    private function seedExpenses(User $admin): void
    {
        $definitions = [
            ['description' => 'Fabric Purchase (Italian Wool)', 'amount' => 45000, 'category' => 'Raw Material', 'payment_method' => 'Bank', 'vendor' => 'Milano Textiles', 'days' => 2],
            ['description' => 'Electricity Bill',               'amount' => 12500, 'category' => 'Utilities',    'payment_method' => 'Bank', 'days' => 5],
            ['description' => 'Staff Salaries',                 'amount' => 85000, 'category' => 'Salary',       'payment_method' => 'Bank', 'days' => 30],
            ['description' => 'Sewing Machine Service',         'amount' => 6500,  'category' => 'Maintenance',  'payment_method' => 'Cash', 'vendor' => 'Singh Repairs', 'days' => 9],
            ['description' => 'Shop Rent',                      'amount' => 55000, 'category' => 'Rent',         'payment_method' => 'Bank', 'days' => 12],
        ];

        foreach ($definitions as $definition) {
            $days = $definition['days'];
            unset($definition['days']);

            Expense::updateOrCreate(
                ['description' => $definition['description']],
                array_merge($definition, [
                    'date'       => Carbon::now()->subDays($days),
                    'created_by' => $admin->id,
                ])
            );
        }
    }

    /**
     * @param array<int, Order> $orders
     */
    private function seedNotifications(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        $definitions = [
            ['title' => 'New Order Created',      'category' => 'orders',   'color' => 'primary', 'icon' => 'fa-solid fa-box',                     'message' => 'Rahul Mehta placed an order for a Sherwani worth ₹45,000', 'read' => false, 'hours' => 1],
            ['title' => 'Payment Received',       'category' => 'payments', 'color' => 'success', 'icon' => 'fa-solid fa-indian-rupee-sign',       'message' => 'Ananya Iyer paid ₹8,000 via UPI for INV-1003',            'read' => false, 'hours' => 4],
            ['title' => 'Order Ready for Pickup', 'category' => 'orders',   'color' => 'info',    'icon' => 'fa-solid fa-arrows-rotate',           'message' => 'ORD-1004 for Zainab Abbas is ready for collection',        'read' => false, 'hours' => 9],
            ['title' => 'Low Stock Alert',        'category' => 'stock',    'color' => 'warning', 'icon' => 'fa-solid fa-boxes-stacked',           'message' => 'Signature Buttons Set is running low — only 8 left',       'read' => true,  'hours' => 26],
            ['title' => 'WhatsApp Notification Sent', 'category' => 'whatsapp', 'color' => 'success', 'icon' => 'fa-brands fa-whatsapp',           'message' => 'Pickup message sent to Zainab Abbas for ORD-1004',         'read' => true,  'hours' => 30],
        ];

        foreach ($definitions as $index => $definition) {
            Notification::updateOrCreate(
                ['title' => $definition['title'], 'message' => $definition['message']],
                [
                    'type'        => ucfirst($definition['category']),
                    'category'    => $definition['category'],
                    'icon'        => $definition['icon'],
                    'color'       => $definition['color'],
                    'is_read'     => $definition['read'],
                    'read_at'     => $definition['read'] ? now() : null,
                    'order_id'    => $orders[$index % count($orders)]->id,
                    'customer_id' => $orders[$index % count($orders)]->customer_id,
                    'created_at'  => Carbon::now()->subHours($definition['hours']),
                ]
            );
        }
    }

    /**
     * @param array<int, Order> $orders
     */
    private function seedActivity(array $orders, User $admin): void
    {
        if (empty($orders)) {
            return;
        }

        $definitions = [
            ['action' => 'Created Order',        'category' => 'orders',    'description' => 'ORD-1002 created for Rahul Mehta (₹45,000)', 'minutes' => 45],
            ['action' => 'Payment recorded',     'category' => 'payments',  'description' => 'Ananya Iyer paid ₹8,000 via UPI',            'minutes' => 190],
            ['action' => 'Order status changed', 'category' => 'orders',    'description' => 'ORD-1004 moved from In Progress to Ready',   'minutes' => 320],
            ['action' => 'Created Customer',     'category' => 'customers', 'description' => 'Sara Ali added to the customer directory',   'minutes' => 640],
            ['action' => 'Updated ProductService', 'category' => 'inventory', 'description' => 'Italian Wool Navy stock adjusted to 24 m', 'minutes' => 900],
        ];

        foreach ($definitions as $definition) {
            $minutes = $definition['minutes'];
            unset($definition['minutes']);

            ActivityLog::updateOrCreate(
                ['action' => $definition['action'], 'description' => $definition['description']],
                array_merge($definition, [
                    'user_id'    => $admin->id,
                    'actor_name' => $admin->name,
                    'event'      => 'seeded',
                    'created_at' => Carbon::now()->subMinutes($minutes),
                ])
            );
        }
    }
}
