<?php
namespace App\Enums;

enum ClothStoreOrderStatus: string
{
    case Pending='Pending'; case Processing='Processing'; case Ready='Ready'; case Completed='Completed';
    case Cancelled='Cancelled'; case Returned='Returned'; case PartiallyReturned='Partially Returned';
    public static function values(): array { return array_column(self::cases(),'value'); }
}
