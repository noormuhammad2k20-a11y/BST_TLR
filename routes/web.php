<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrintingController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PwaController;

Route::get('license', [\App\Http\Controllers\LicenseController::class, 'show'])
    ->withoutMiddleware(\App\Http\Middleware\ApplyShopSettings::class)->name('license.show');
Route::post('license', [\App\Http\Controllers\LicenseController::class, 'install'])
    ->withoutMiddleware(\App\Http\Middleware\ApplyShopSettings::class)
    ->middleware('throttle:6,1')->name('license.install');

/*
|--------------------------------------------------------------------------
| PWA — must stay public so the app is installable before sign-in
|--------------------------------------------------------------------------
*/
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'business'])->group(function () {
    Route::get('receipts/branding/{kind}', [SettingController::class, 'branding'])
        ->whereIn('kind', ['logo', 'stamp'])->name('receipts.branding');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    /* -------------------- Live / polling endpoints -------------------- */
    Route::post('orders/{order}/retry-sms', [OrderController::class, 'retrySms'])->name('orders.retry-sms');

    Route::prefix('live')->name('live.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'live'])->name('dashboard');
        Route::get('counters', [DashboardController::class, 'counters'])->name('counters');
        Route::get('notifications', [NotificationController::class, 'live'])->name('notifications');
        Route::get('orders', [OrderController::class, 'live'])->name('orders');
    });

    Route::get('search', [SearchController::class, 'index'])->name('search');

    /* ----------------------------- Customers -------------------------- */
    /* The import wizard is declared before the resource so its fixed paths
       can never be read as a customer id. */
    Route::get('customers/import', [ImportController::class, 'index'])->name('customers.import');
    Route::get('customers/import/template', [ImportController::class, 'template'])->name('customers.import.template');
    Route::post('customers/import/upload', [ImportController::class, 'upload'])->name('customers.import.upload');
    Route::post('customers/import/run', [ImportController::class, 'run'])->name('customers.import.run');
    Route::post('customers/import/finish', [ImportController::class, 'finish'])->name('customers.import.finish');
    Route::post('customers/import/discard', [ImportController::class, 'discard'])->name('customers.import.discard');
    Route::get('customers/import/{token}/issues', [ImportController::class, 'issues'])->name('customers.import.issues');

    Route::get('customers/{customer}/ledger', [\App\Http\Controllers\CustomerLedgerController::class,'show'])->name('customers.ledger');
    Route::post('customers/{customer}/ledger/payments', [\App\Http\Controllers\CustomerLedgerController::class,'receive'])->name('customers.ledger.payments');
    Route::post('customers/{customer}/ledger/charges', [\App\Http\Controllers\CustomerLedgerController::class,'charge'])->name('customers.ledger.charges');
    Route::get('customers/{customer}/summary', [CustomerController::class, 'summary'])
        ->name('customers.summary');
    Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->whereNumber('customer')->name('customers.restore');
    Route::delete('customers/{customer}/permanent', [CustomerController::class, 'permanentlyDelete'])->whereNumber('customer')->name('customers.permanent');
    Route::post('customers/{customer}/sms', [CustomerController::class, 'sendSms'])
        ->middleware('throttle:6,1')->name('customers.sms');
    Route::resource('customers', CustomerController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    /* ------------------------------ Orders ---------------------------- */
    Route::post('orders/bulk-notify', [OrderController::class, 'bulkNotify'])->name('orders.bulk-notify');
    Route::post('orders/bulk-extend', [OrderController::class, 'bulkExtend'])->name('orders.bulk-extend');
    Route::post('orders/bulk-status', [OrderController::class, 'bulkStatus'])->name('orders.bulk-status');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{order}/notify', [OrderController::class, 'notify'])->name('orders.notify');
    Route::get('orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
    Route::resource('orders', OrderController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    /* --------------------------- Measurements ------------------------- */
    Route::resource('measurements', MeasurementController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    /* ------------------------ Products & Services --------------------- */
    Route::post('products-services/categories', [ProductServiceController::class, 'storeCategory'])->name('products-services.categories.store');
    Route::put('products-services/categories/{category}', [ProductServiceController::class, 'updateCategory'])->name('products-services.categories.update');
    Route::delete('products-services/categories/{category}', [ProductServiceController::class, 'destroyCategory'])->name('products-services.categories.destroy');
    Route::post('products-services/rates', [ProductServiceController::class, 'storeRate'])->name('products-services.rates.store');
    Route::put('products-services/rates/{rate}', [ProductServiceController::class, 'updateRate'])->name('products-services.rates.update');
    Route::delete('products-services/rates/{rate}', [ProductServiceController::class, 'destroyRate'])->name('products-services.rates.destroy');
    Route::resource('products-services', ProductServiceController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    /* ------------------------------ Finance --------------------------- */
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');

    Route::post('payments-billing/{order}/record', [PaymentController::class, 'record'])
        ->name('payments-billing.record');
    Route::get('payments-billing/{order}/invoice', [PaymentController::class, 'invoice'])
        ->name('payments-billing.invoice');
    Route::resource('payments-billing', PaymentController::class)
        ->only(['index', 'destroy']);

    Route::resource('expenses', ExpenseController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    /* ------------------------------ Reports --------------------------- */
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/data', [ReportController::class, 'data'])->name('reports.data');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('reports/table', [ReportController::class, 'table'])->name('reports.table');
    Route::post('reports/target', [ReportController::class, 'target'])->name('reports.target');

    /* ----------------------------- Delivery --------------------------- */
    Route::get('delivery/orders/{order}/receipt', [DeliveryController::class, 'receipt'])->name('delivery.receipt');
    Route::get('delivery/sms-history',[DeliveryController::class,'history'])->name('delivery.sms-history');
    Route::post('delivery/sms/{sms}/resolve',[DeliveryController::class,'resolveSms'])->name('delivery.sms.resolve');
    Route::post('delivery/orders/{order}/collect',[DeliveryController::class,'collect'])->name('delivery.collect');
    Route::patch('delivery/{delivery}/status', [DeliveryController::class, 'updateStatus'])
        ->name('delivery.status');
    // Collection notices for several customers at once. These garments are
    // collected from the shop, so there is no courier route to assign.
    Route::post('delivery/bulk-notify', [DeliveryController::class, 'bulkNotify'])
        ->name('delivery.bulk-notify');
    Route::resource('delivery', DeliveryController::class)
        ->only(['index', 'destroy']);

    /* ------------------------------- Staff ---------------------------- */
    /* Specific segments are declared before the {staff} wildcard so a path
       like `staff-payments/3` can never be read as a staff member named
       "staff-payments". */
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');

    Route::delete('staff-payments/{payment}', [StaffController::class, 'destroyPayment'])
        ->name('staff.payments.destroy');
    Route::delete('staff-work/{work}', [StaffController::class, 'destroyWork'])
        ->name('staff.work.destroy');

    Route::get('staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
    Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::patch('staff/{staff}/active', [StaffController::class, 'toggleActive'])->name('staff.active');
    Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    Route::post('staff/{staff}/payments', [StaffController::class, 'storePayment'])->name('staff.payments.store');
    Route::post('staff/{staff}/advances', [StaffController::class, 'storeAdvance'])->name('staff.advances.store');
    Route::delete('staff-advances/{advance}', [StaffController::class, 'destroyAdvance'])->name('staff.advances.destroy');
    Route::post('staff/{staff}/work', [StaffController::class, 'storeWork'])->name('staff.work.store');

    /* --------------------------- Notifications ------------------------ */
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    /* -------------------------- Printing centre ----------------------- */
    Route::get('printing-center', [PrintingController::class, 'index'])->name('printing-center.index');
    Route::get('printing-center/render', [PrintingController::class, 'render'])->name('printing-center.render');

    /* ------------------------------ Profile --------------------------- */
    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    /* ----------------------------- Settings --------------------------- */
    Route::middleware('role:admin')->group(function () {
        Route::get('settings/sidebar-appearance', [\App\Http\Controllers\SidebarAppearanceController::class, 'show'])->name('settings.sidebar-appearance.show');
        Route::put('settings/sidebar-appearance', [\App\Http\Controllers\SidebarAppearanceController::class, 'update'])->name('settings.sidebar-appearance.update');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/upload', [SettingController::class, 'upload'])->name('settings.upload');
        Route::post('settings/upload/remove', [SettingController::class, 'removeUpload'])->name('settings.upload.remove');
        Route::post('settings/reset', [SettingController::class, 'reset'])->name('settings.reset');


        /* SMS */
        Route::post('settings/sms/test', [SettingController::class, 'testSms'])->middleware('throttle:6,1')->name('settings.sms.test');
        Route::post('settings/sms/send-test', [SettingController::class, 'sendTestSms'])->middleware('throttle:6,1')->name('settings.sms.send-test');
        Route::get('settings/sms/balance', [SettingController::class, 'smsBalance'])->middleware('throttle:6,1')->name('settings.sms.balance');

        /* Backup & data */
        Route::get('settings/backup', [SettingController::class, 'backup'])->name('settings.backup');
        Route::get('settings/backup/json', [SettingController::class, 'backupJson'])->name('settings.backup.json');
        Route::post('settings/backup/inspect', [SettingController::class, 'inspectBackup'])->name('settings.backup.inspect');
        Route::post('settings/backup/restore', [SettingController::class, 'restore'])->name('settings.backup.restore');
        Route::post('settings/purge', [SettingController::class, 'purge'])->name('settings.purge');
        Route::post('settings/clear-notifications', [SettingController::class, 'clearNotifications'])
            ->name('settings.clear-notifications');

    });

    /*
    |--------------------------------------------------------------------------
    | Cloth Store Management System
    |--------------------------------------------------------------------------
    */
    Route::prefix('cloth-store')->name('cloth-store.')->group(function () {
        // Dashboard & Reports
        Route::get('/', [\App\Http\Controllers\ClothStore\DashboardController::class, 'index'])->name('dashboard');
        Route::get('reports', [\App\Http\Controllers\ClothStore\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/pdf', [\App\Http\Controllers\ClothStore\ReportController::class, 'pdf'])->name('reports.pdf');


        // Products & Categories
        Route::resource('categories', \App\Http\Controllers\ClothStore\CategoryController::class);
        Route::resource('products', \App\Http\Controllers\ClothStore\ProductController::class);

        // Stock Management
        Route::get('stock', [\App\Http\Controllers\ClothStore\StockController::class, 'index'])->name('stock.index');
        Route::get('stock/history', [\App\Http\Controllers\ClothStore\StockController::class, 'history'])->name('stock.history');
        Route::post('stock/transaction', [\App\Http\Controllers\ClothStore\StockController::class, 'transaction'])->name('stock.transaction');
        Route::get('stock/alerts', [\App\Http\Controllers\ClothStore\StockController::class, 'alerts'])->name('stock.alerts');
        Route::post('stock/alerts/{id}/ignore', [\App\Http\Controllers\ClothStore\StockController::class, 'ignoreAlert'])->name('stock.alerts.ignore');
        Route::post('stock/alerts/{id}/config', [\App\Http\Controllers\ClothStore\StockController::class, 'updateReorderConfig'])->name('stock.alerts.config');



        /* ------------------------------ Sales ------------------------------
         | Shop-floor work: any authenticated staff member may ring up a sale,
         | view orders and process a return. These deliberately sit OUTSIDE the
         | role:admin group below — when they lived inside it, a cashier hit a
         | 403 on the POS and could not complete a single transaction.
         */
        Route::get('orders', [\App\Http\Controllers\ClothStore\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [\App\Http\Controllers\ClothStore\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/status', [\App\Http\Controllers\ClothStore\OrderController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::post('orders/{order}/payment', [\App\Http\Controllers\ClothStore\OrderController::class, 'recordPayment'])->name('orders.payment');

        // Point of sale
        Route::get('checkout', [\App\Http\Controllers\ClothStore\CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('checkout', [\App\Http\Controllers\ClothStore\CheckoutController::class, 'store'])->name('checkout.store');
        // Live lookups so the till queries the database instead of filtering
        // a copy of the product table held in the browser.
        Route::get('checkout/products', [\App\Http\Controllers\ClothStore\CheckoutController::class, 'searchProducts'])->name('checkout.products');
        Route::get('checkout/scan', [\App\Http\Controllers\ClothStore\CheckoutController::class, 'scan'])->name('checkout.scan');
        Route::get('checkout/customers', [\App\Http\Controllers\ClothStore\CheckoutController::class, 'searchCustomers'])->name('checkout.customers');

        // Returns
        Route::get('returns-search', [\App\Http\Controllers\ClothStore\ReturnController::class, 'searchOrder'])->name('returns.search');
        Route::post('returns/{id}/status', [\App\Http\Controllers\ClothStore\ReturnController::class, 'updateStatus'])->name('returns.status');
        Route::resource('returns', \App\Http\Controllers\ClothStore\ReturnController::class)->except(['create', 'edit', 'destroy']);

        // Discounts & Offers
        Route::resource('discounts', \App\Http\Controllers\ClothStore\DiscountController::class)->except(['create', 'show', 'edit']);
        Route::post('discounts/{id}/toggle', [\App\Http\Controllers\ClothStore\DiscountController::class, 'toggleStatus'])->name('discounts.toggle');


        // Customers
        // The literal segments are declared before the {customer} routes so
        // "quick" and "ledger" are never captured as a customer id.
        Route::get('customers', [\App\Http\Controllers\ClothStore\CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers/quick', [\App\Http\Controllers\ClothStore\CustomerController::class, 'storeQuick'])->name('customers.quick');
        Route::get('customers/ledger/{id?}', [\App\Http\Controllers\ClothStore\CustomerController::class, 'ledger'])->name('customers.ledger');
        Route::post('customers', [\App\Http\Controllers\ClothStore\CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [\App\Http\Controllers\ClothStore\CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [\App\Http\Controllers\ClothStore\CustomerController::class, 'destroy'])->name('customers.destroy');
        
        // Finance
        Route::get('expenses', [\App\Http\Controllers\ClothStore\ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [\App\Http\Controllers\ClothStore\ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [\App\Http\Controllers\ClothStore\ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [\App\Http\Controllers\ClothStore\ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::put('expenses/{expense}/status', [\App\Http\Controllers\ClothStore\ExpenseController::class, 'updateStatus'])->name('expenses.status');
        
        Route::get('payments', [\App\Http\Controllers\ClothStore\PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [\App\Http\Controllers\ClothStore\PaymentController::class, 'store'])->name('payments.store');
        Route::put('payments/{payment}/reverse', [\App\Http\Controllers\ClothStore\PaymentController::class, 'reverse'])->name('payments.reverse');
        Route::get('payments/customer/{id}', [\App\Http\Controllers\ClothStore\PaymentController::class, 'getCustomerDetails'])->name('payments.customer');

        // System
        Route::middleware('role:admin')->group(function () {
            Route::get('settings', [\App\Http\Controllers\ClothStore\SettingController::class, 'index'])->name('settings.index');
            Route::post('settings', [\App\Http\Controllers\ClothStore\SettingController::class, 'store'])->name('settings.store');

            // NOTE: orders, checkout and returns used to be duplicated here.
            // Because these route names were registered twice, the admin-only
            // copy won and silently 403'd every non-admin user. They now live
            // once, above, outside this group.

            Route::get('loyalty', [\App\Http\Controllers\ClothStore\LoyaltyController::class, 'index'])->name('loyalty.index');
            Route::post('loyalty/adjust', [\App\Http\Controllers\ClothStore\LoyaltyController::class, 'adjust'])->name('loyalty.adjust');
        });
    });
});
