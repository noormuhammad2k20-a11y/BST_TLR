<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;

/**
 * Central audit trail. Every meaningful mutation in the system funnels through
 * here so Activity Logs and the dashboard feed are always truthful.
 */
class ActivityLogger
{
    public static function log(
        string $action,
        string $description,
        string $category = 'system',
        ?Model $subject = null,
        array $properties = [],
        ?string $event = null,
    ): ActivityLog {
        $user = Auth::user();

        $log = ActivityLog::create([
            'action'       => $action,
            'description'  => $description,
            'category'     => $category,
            'event'        => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'properties'   => $properties ?: null,
            'user_id'      => $user?->id,
            'actor_name'   => $user?->name ?? 'System',
            'ip_address'   => Request::ip(),
        ]);

        Cache::forget('dashboard.activity_feed');

        return $log;
    }

    public static function created(Model $subject, string $description, string $category): ActivityLog
    {
        return self::log('Created ' . class_basename($subject), $description, $category, $subject, [], 'created');
    }

    public static function updated(Model $subject, string $description, string $category, array $changes = []): ActivityLog
    {
        return self::log('Updated ' . class_basename($subject), $description, $category, $subject, $changes, 'updated');
    }

    public static function deleted(Model $subject, string $description, string $category): ActivityLog
    {
        return self::log('Deleted ' . class_basename($subject), $description, $category, $subject, [], 'deleted');
    }
}
