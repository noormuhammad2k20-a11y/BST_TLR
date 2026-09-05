<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Measurement;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use App\Services\Settings;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Settings::forClient();

        $dataCounts = BackupService::counts();

        $measurementFields = Measurement::FIELDS;
        $timezones = \DateTimeZone::listIdentifiers();

        // A real recent order drives the template and receipt previews, so what
        // the shop sees here is what its customers will actually receive.
        $previewOrder = Order::with('customer')->latest()->first();
        $previewVariables = $previewOrder
            ? WhatsAppService::variablesForOrder($previewOrder)
            : WhatsAppService::shopVariables();

        return view('settings.index', [
            'settings'          => $settings,
            'dataCounts'        => $dataCounts,
            'measurementFields' => $measurementFields,
            'timezones'         => $timezones,
            'previewVariables'  => $previewVariables,
            'previewOrder'      => $previewOrder,
            'templateVariables' => Settings::templateVariables(),
            'defaultTemplates'  => Settings::defaultTemplates(),
            'defaultSmsTemplates' => Settings::defaultSmsTemplates(),
            'backupTypes'       => BackupService::typesForClient(),
            'whatsappReady'     => WhatsAppService::configured(),
            'smsReady'          => SmsService::configured(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $rules = [];
        foreach (Settings::SCHEMA as $key => $meta) {
            if ($request->has($key)) {
                $rules[$key] = $meta['rule'];
            }
        }

        $validated = $request->validate($rules);

        // Only persist what was actually submitted, so saving one panel can
        // never blank out another panel's values.
        $payload = [];
        foreach (Settings::SCHEMA as $key => $meta) {
            if (!$request->has($key)) {
                continue;
            }

            // A masked secret means "unchanged" — never overwrite a real token
            // with the dots we sent to the browser.
            if (!empty($meta['secret']) && preg_match('/^•+$/u', (string) $request->input($key))) {
                continue;
            }

            $payload[$key] = str_contains($meta['rule'], 'boolean')
                ? $request->boolean($key)
                : ($validated[$key] ?? ($meta['json'] ?? false ? [] : ''));
        }

        if (!$payload) {
            return response()->json([
                'success'  => true,
                'message'  => 'Nothing to update.',
                'settings' => Settings::forClient(),
            ]);
        }

        Settings::put($payload);

        ActivityLogger::log(
            'Settings updated',
            sprintf('%d setting(s) changed: %s', count($payload), implode(', ', array_slice(array_keys($payload), 0, 8))),
            'system',
            null,
            array_keys($payload),
            'updated'
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Settings saved successfully.',
            'settings' => Settings::forClient(),
        ]);
    }

    /**
     * Shop logo / stamp upload. Stored on the public disk and referenced by URL.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:logo,stamp',
            'file' => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
        ]);

        $key = $validated['type'] . '_path';

        $this->deleteStoredImage(Setting::getValue($key));

        $path = $request->file('file')->store('branding', 'public');
        $url  = Storage::url($path);

        Settings::put([$key => $url]);

        ActivityLogger::log('Settings updated', ucfirst($validated['type']) . ' image updated', 'system', null, [], 'updated');

        return response()->json([
            'success' => true,
            'message' => ucfirst($validated['type']) . ' uploaded successfully.',
            'url'     => $url,
        ]);
    }

    public function removeUpload(Request $request): JsonResponse
    {
        $validated = $request->validate(['type' => 'required|in:logo,stamp']);
        $key = $validated['type'] . '_path';

        $this->deleteStoredImage(Setting::getValue($key));

        Settings::put([$key => '']);

        return response()->json(['success' => true, 'message' => ucfirst($validated['type']) . ' removed.']);
    }

    /**
     * Restore a panel's keys to their shipped defaults.
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys'   => 'nullable|array',
            'keys.*' => 'string',
            'group'  => 'nullable|string',
        ]);

        // A group name resets every key in that panel, including ones the DOM
        // scan cannot see (structured JSON, secrets, hidden conditionals).
        $keys = filled($validated['group'] ?? null)
            ? Settings::keysInGroup($validated['group'])
            : ($validated['keys'] ?? []);

        if (!$keys) {
            return response()->json(['success' => false, 'message' => 'Nothing to reset.'], 422);
        }

        $payload = [];
        foreach ($keys as $key) {
            if (!isset(Settings::SCHEMA[$key])) {
                continue;
            }

            $default = Settings::SCHEMA[$key]['default'];
            $payload[$key] = $default === null ? [] : $default;
        }

        Settings::put($payload);

        ActivityLogger::log(
            'Settings restored',
            sprintf('%d setting(s) returned to defaults', count($payload)),
            'system',
            null,
            array_keys($payload),
            'updated'
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Restored to defaults.',
            'settings' => Settings::forClient(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  WhatsApp                                                           */
    /* ------------------------------------------------------------------ */

    /** Verifies the configured provider is reachable, without messaging anyone. */
    public function testWhatsapp(): JsonResponse
    {
        if (WhatsAppService::provider() === 'manual') {
            return response()->json([
                'success' => true,
                'message' => 'Manual mode needs no setup — messages open in WhatsApp Web.',
            ]);
        }

        $result = WhatsAppService::testConnection();

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? 'Connected — ' . $result['status'] . '.'
                : $result['error'],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Live gateway state, polled by the Settings panel so the QR appears and
     * disappears on its own as the phone is linked.
     */
    public function gatewayStatus(): JsonResponse
    {
        $gateway = WhatsAppService::gatewayStatus();

        return response()->json([
            'success' => true,
            'gateway' => array_merge($gateway, $this->gatewayDiagnostics()),
            'log'     => $this->gatewayLog(),
        ]);
    }

    /**
     * The things that go wrong before the gateway ever answers.
     *
     * When the panel says "not running", the reason is almost always one of
     * three: the folder was never installed, the token in Settings does not
     * match config.json, or nobody started it. Each is answerable from disk
     * without asking the gateway anything, so the panel can say which it is
     * instead of leaving the shop to guess.
     *
     * @return array<string, mixed>
     */
    private function gatewayDiagnostics(): array
    {
        $dir    = base_path('whatsapp-gateway');
        $config = $dir . DIRECTORY_SEPARATOR . 'config.json';

        $installed = is_dir($dir . DIRECTORY_SEPARATOR . 'node_modules');
        $hasConfig = is_file($config);

        // Whether the token saved here is the one the gateway will accept. The
        // token itself is never returned — only whether the two agree.
        $tokenMatches = null;

        if ($hasConfig) {
            $parsed = json_decode((string) @file_get_contents($config), true);
            $onDisk = is_array($parsed) ? (string) ($parsed['token'] ?? '') : '';
            $saved  = Settings::str('gateway_token');

            if ($onDisk !== '' && $saved !== '') {
                $tokenMatches = hash_equals($onDisk, $saved);
            }
        }

        return [
            'folder_present' => is_dir($dir),
            'installed'      => $installed,
            'has_config'     => $hasConfig,
            'token_matches'  => $tokenMatches,
            'provider'       => WhatsAppService::provider(),
        ];
    }

    /**
     * The tail of the gateway's own log, read straight off disk.
     *
     * The gateway writes it next to its own script, which means the panel can
     * show it without another endpoint — and without the shop opening a second
     * window to find out why a message did not go out.
     *
     * @return array<int, string>
     */
    private function gatewayLog(int $lines = 40): array
    {
        $path = base_path('whatsapp-gateway' . DIRECTORY_SEPARATOR . 'gateway.log');

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        // Only the tail is ever wanted, and the file grows without bound, so
        // the last chunk is read rather than the whole thing.
        $size   = filesize($path);
        $chunk  = min($size, 64 * 1024);
        $handle = @fopen($path, 'rb');

        if (!$handle) {
            return [];
        }

        fseek($handle, -$chunk, SEEK_END);
        $tail = (string) fread($handle, $chunk);
        fclose($handle);

        $rows = preg_split('/\r?\n/', trim($tail)) ?: [];

        return array_values(array_slice($rows, -$lines));
    }

    /**
     * Send one arbitrary message to one number, through whatever provider is
     * configured.
     *
     * Deliberately not routed through `testTemplate`: that needs a template to
     * exist and be switched on, which is exactly what a shop cannot rely on
     * while it is still proving the link works at all.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'   => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = WhatsAppService::send($validated['phone'], $validated['message']);

        return response()->json([
            'success'  => $result['sent'] || (bool) $result['url'],
            'provider' => $result['provider'],
            'url'      => $result['url'],
            'message'  => $result['sent']
                ? 'Sent. It should arrive on that phone within a few seconds.'
                : ($result['url']
                    ? 'Manual mode: opening WhatsApp Web with the message ready.'
                    : $result['error']),
        ], $result['sent'] || $result['url'] ? 200 : 422);
    }

    /** Unlinks the phone so a different number can be paired. */
    public function gatewayLogout(): JsonResponse
    {
        $result = WhatsAppService::gatewayLogout();

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? 'Phone unlinked. Scan the new QR code to link another number.'
                : $result['error'],
        ], $result['ok'] ? 200 : 422);
    }

    /* ------------------------------------------------------------------ */
    /*  SMS                                                                */
    /* ------------------------------------------------------------------ */

    /** Verifies the configured SMS provider is reachable, without sending anything. */
    public function testSms(): JsonResponse
    {
        $result = SmsService::testConnection();

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? $result['status']
                : $result['error'],
        ], $result['ok'] ? 200 : 422);
    }

    /** Send one test SMS to a given number. */
    public function sendTestSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'   => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = SmsService::send($validated['phone'], $validated['message']);

        return response()->json([
            'success'  => $result['sent'],
            'provider' => $result['provider'],
            'message'  => $result['sent']
                ? 'SMS sent successfully. It should arrive within a few seconds.'
                : ($result['error'] ?? 'Could not send the SMS.'),
        ], $result['sent'] ? 200 : 422);
    }

    /** Fetch SMS balance / package information from the active provider. */
    public function smsBalance(): JsonResponse
    {
        $result = SmsService::balance();

        return response()->json([
            'success' => $result['ok'],
            'data'    => $result['data'] ?? [],
            'message' => $result['ok']
                ? 'Balance retrieved.'
                : ($result['error'] ?? 'Could not check the balance.'),
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Sends one template to a nominated number so the shop can see exactly what
     * lands, using the same code path as a real notification.
     */
    public function testTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|string',
            'phone'       => 'nullable|string|max:50',
            'text'        => 'nullable|string|max:2000',
        ]);

        $order = Order::with('customer')->latest()->first();

        $variables = $order
            ? WhatsAppService::variablesForOrder($order)
            : WhatsAppService::shopVariables();

        // Prefer the unsaved text in the editor so a draft can be tested.
        $body = filled($validated['text'] ?? null)
            ? WhatsAppService::render($validated['text'], $variables)
            : WhatsAppService::renderTemplate($validated['template_id'], $variables);

        if ($body === null) {
            return response()->json(['success' => false, 'message' => 'That template is switched off.'], 422);
        }

        $phone = $validated['phone']
            ?: Settings::str('whatsapp_number')
            ?: Settings::str('phone');

        if (blank($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Add a WhatsApp number in Business Profile, or type one to test against.',
            ], 422);
        }

        $result = WhatsAppService::send($phone, $body);

        return response()->json([
            'success'  => $result['sent'],
            'provider' => $result['provider'],
            'url'      => $result['url'],
            'preview'  => $body,
            'message'  => $result['sent']
                ? ($result['provider'] === 'manual' ? 'Opening WhatsApp Web with the message ready.' : 'Test message sent via UltraMsg.')
                : $result['error'],
        ], $result['sent'] || $result['url'] ? 200 : 422);
    }

    /* ------------------------------------------------------------------ */
    /*  Backup & data                                                      */
    /* ------------------------------------------------------------------ */

    /** Human-readable CSV export, for spreadsheets. */
    public function backup(): StreamedResponse
    {
        return BackupService::streamCsv();
    }

    /** Machine-readable JSON export, the format `restore` accepts back. */
    public function backupJson(): StreamedResponse
    {
        ActivityLogger::log('Backup exported', 'Full JSON backup downloaded', 'system', null, [], 'created');

        return BackupService::streamJson();
    }

    /** Reports what a backup file contains before anything is written. */
    public function inspectBackup(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:json,txt|max:20480']);

        $result = BackupService::inspect($request->file('file')->getRealPath());

        return response()->json(
            $result + ['success' => $result['valid']],
            $result['valid'] ? 200 : 422
        );
    }

    public function restore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'    => 'required|file|mimes:json,txt|max:20480',
            'mode'    => 'required|in:merge,replace',
            'types'   => 'required|array|min:1',
            'types.*' => 'string|in:' . implode(',', array_keys(BackupService::TYPES)),
        ]);

        $result = BackupService::restore(
            $request->file('file')->getRealPath(),
            $validated['types'],
            $validated['mode']
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        ActivityLogger::log(
            'Backup restored',
            sprintf('%s restore: %s', ucfirst($validated['mode']), collect($result['imported'])->map(fn ($n, $t) => "$n $t")->implode(', ')),
            'system',
            null,
            $result['imported'],
            'updated'
        );

        return response()->json($result + ['counts' => BackupService::counts()]);
    }

    /**
     * Deletes selected data types. Guarded by a typed confirmation on the
     * client and re-checked here, because it cannot be undone.
     */
    public function purge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'types'   => 'required|array|min:1',
            'types.*' => 'string|in:' . implode(',', array_keys(BackupService::TYPES)),
            'confirm' => 'required|string',
        ]);

        if (trim($validated['confirm']) !== 'DELETE') {
            return response()->json([
                'success' => false,
                'message' => 'Type DELETE exactly to confirm.',
            ], 422);
        }

        $deleted = BackupService::purge($validated['types']);

        ActivityLogger::log(
            'Data deleted',
            'Danger Zone purge: ' . collect($deleted)->map(fn ($n, $t) => "$n $t")->implode(', '),
            'system',
            null,
            $deleted,
            'deleted'
        );

        return response()->json([
            'success' => true,
            'message' => sprintf('%s record(s) permanently deleted.', number_format(array_sum($deleted))),
            'deleted' => $deleted,
            'counts'  => BackupService::counts(),
        ]);
    }

    /**
     * Clears every notification. Kept behind the Danger Zone.
     */
    public function clearNotifications(): JsonResponse
    {
        $count = Notification::count();
        Notification::query()->delete();

        \App\Services\NotificationService::flushCache();
        ActivityLogger::log('Notifications cleared', "{$count} notification(s) removed", 'system', null, [], 'deleted');

        return response()->json([
            'success' => true,
            'message' => "{$count} notification(s) cleared.",
            'counts'  => BackupService::counts(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** Removes a previously uploaded branding image from the public disk. */
    private function deleteStoredImage(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH) ?? '');

        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
