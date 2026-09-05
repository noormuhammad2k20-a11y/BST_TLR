<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class Access
{
    public const CS = [
        'dashboard'=>'Dashboard - View', 'categories'=>'Products - View','products'=>'Products - View',
        'stock'=>'Stock - View','checkout'=>'Sales - Create Sale','orders'=>'Sales - View',
        'returns'=>'Sales - Return','returns.search'=>'Sales - Return','discounts'=>'Discounts - View',
        'customers'=>'Customers - View','payments'=>'Finance - View Payments','expenses'=>'Finance - View Expenses',
        'reports'=>'Reports - View','settings'=>'Settings - View','loyalty'=>'Loyalty - Manage',
    ];

    public static function permission(Request $request): ?string
    {
        $name=substr((string)$request->route()?->getName(),12);
        [$module,$action]=array_pad(explode('.',$name,2),2,'index');
        if ($module==='stock' && $action==='transaction') return match($request->input('operation')) {
            'in'=>'Stock - Stock In','out'=>'Stock - Stock Out', default=>'Stock - Adjustment'};
        $specific=[
            'payments.store'=>'Customers - Collect Payment','payments.reverse'=>'Finance - Reverse Payment',
            'orders.payment'=>'Customers - Collect Payment','orders.updateStatus'=>$request->input('status')==='Cancelled'?'Sales - Cancel Sale':'Sales - Edit Sale',
            'returns.status'=>'Sales - Approve Return','customers.quick'=>'Customers - Create',
            'customers.ledger'=>'Finance - View Payments','reports.pdf'=>'Reports - Export',
            'stock.alerts.ignore'=>'Stock - Adjustment','stock.alerts.config'=>'Stock - Adjustment',
            'expenses.status'=>'Finance - Edit Expenses',
        ];
        if (isset($specific[$name])) return $specific[$name];
        $verbs=['store'=>'Create','update'=>'Edit','destroy'=>'Delete','toggle'=>'Edit','create'=>'Create','edit'=>'Edit'];
        if (isset($verbs[$action])) {
            $label=match($module) {'products','categories'=>'Products','customers'=>'Customers','discounts'=>'Discounts',
                default=>null};
            if ($label) return $label.' - '.$verbs[$action];
            if ($module==='expenses') return 'Finance - '.$verbs[$action].' Expenses';
            if ($module==='settings') return 'Settings - Modify';
        }
        return self::CS[$module]??null;
    }

    public static function allowed(User $user,string $permission): bool
    {
        return $user->isAdmin() || $user->hasCsPermission($permission);
    }
}
