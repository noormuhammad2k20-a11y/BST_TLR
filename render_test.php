<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$services = \App\Models\ProductService::all()->map(function ($s, $idx) {
    $colors = [['bg-indigo-50', 'text-indigo-600'],['bg-red-50', 'text-red-600'],['bg-sky-50', 'text-sky-600'],['bg-emerald-50', 'text-emerald-600']];
    $color = $colors[$idx % count($colors)];
    return [
        'id' => 'srv-' . $s->id,
        'name' => $s->name,
        'icon' => str_contains(strtolower($s->name), 'suit') ? 'fa-vest' : 'fa-shirt',
        'iconBg' => $color[0],
        'iconColor' => $color[1],
        'category' => $s->type == 'service' ? 'Service' : 'Product',
        'delivery' => '5-7 days',
        'orders' => rand(10, 100),
        'price' => $s->price
    ];
});

$html = view('products-services.index', compact('services'))->render();
file_put_contents('rendered_view.html', $html);
echo "Done";
