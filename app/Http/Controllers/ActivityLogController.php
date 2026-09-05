<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category', 'all');
        $search   = $request->query('q');

        $activities = ActivityLog::query()
            ->with('user:id,name')
            ->ofCategory($category)
            ->search($search)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $counts = ActivityLog::query()
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $tabs = [
            'all'       => ['label' => 'All Activity',   'count' => $counts->sum()],
            'auth'      => ['label' => 'Login History',  'count' => $counts['auth'] ?? 0],
            'orders'    => ['label' => 'Orders',         'count' => $counts['orders'] ?? 0],
            'customers' => ['label' => 'Customers',      'count' => $counts['customers'] ?? 0],
            'inventory' => ['label' => 'Inventory',      'count' => $counts['inventory'] ?? 0],
        ];

        // Filtering and paging swap only the timeline, never the whole page.
        if ($request->ajax() || $request->boolean('partial')) {
            return response()->json([
                'html'  => view('activity-logs.partials.timeline', compact('activities', 'category', 'search'))->render(),
                'tabs'  => $tabs,
            ]);
        }

        return view('activity-logs.index', compact('activities', 'tabs', 'category', 'search'));
    }

    /**
     * Streamed CSV so large audit trails never exhaust memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $category = $request->query('category', 'all');
        $search   = $request->query('q');

        $filename = 'activity-logs-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($category, $search) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['When', 'Category', 'Action', 'Description', 'By', 'IP']);

            ActivityLog::query()
                ->with('user:id,name')
                ->ofCategory($category)
                ->search($search)
                ->latest()
                ->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $log) {
                        fputcsv($handle, [
                            $log->created_at->format('Y-m-d H:i:s'),
                            $log->category,
                            $log->action,
                            $log->description,
                            $log->actor_label,
                            $log->ip_address,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
