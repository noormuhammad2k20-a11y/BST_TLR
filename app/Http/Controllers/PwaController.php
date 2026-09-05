<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    /**
     * The web app manifest. Served from a route rather than a static file so the
     * installed app carries the shop name configured in Settings.
     *
     * Deliberately outside the auth middleware: the browser fetches the manifest
     * before sign-in, and an auth redirect would make the app un-installable.
     */
    public function manifest(): JsonResponse
    {
        $name = (string) Setting::getValue('store_name', 'Atelier');

        return response()->json([
            'name'             => $name . ' — Admin Suite',
            'short_name'       => $name,
            'description'      => 'Tailor management system: orders, customers, measurements, billing and delivery.',
            'id'               => '/',
            'start_url'        => '/',
            'scope'            => '/',
            'display'          => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation'      => 'any',
            'background_color' => '#F8FAFC',
            'theme_color'      => '#0F172A',
            'categories'       => ['business', 'productivity'],
            'lang'             => 'en',
            'dir'              => 'ltr',

            'icons' => [
                [
                    'src'     => asset('icons/icon-192.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => asset('icons/icon-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => asset('icons/icon-maskable-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],

            // Right-click the taskbar/dock icon to jump straight to a section.
            'shortcuts' => [
                [
                    'name'  => 'New Order',
                    'url'   => '/orders?action=create',
                    'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name'  => 'Customers',
                    'url'   => '/customers',
                    'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']],
                ],
                [
                    'name'  => 'Payments & Billing',
                    'url'   => '/payments-billing',
                    'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']],
                ],
            ],
        ])->withHeaders([
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
