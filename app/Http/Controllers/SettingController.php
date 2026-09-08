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
            ? \App\Services\NotificationVariables::variablesForOrder($previewOrder)
            : \App\Services\NotificationVariables::shopVariables();

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

        $events = array_column(Settings::defaultTemplates(), 'id');
        $variables = array_keys(Settings::templateVariables());
        if ($request->has('meta_templates')) {
            $rules += [
                'meta_templates.*' => 'required|array:id,active,name,language,parameters',
                'meta_templates.*.id' => ['required', 'distinct', \Illuminate\Validation\Rule::in($events)],
                'meta_templates.*.active' => 'required|boolean',
                'meta_templates.*.name' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
                'meta_templates.*.language' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2,3}(?:_[A-Za-z]{2,4})?$/'],
                'meta_templates.*.parameters' => 'present|array|max:30',
                'meta_templates.*.parameters.*' => ['required', \Illuminate\Validation\Rule::in($variables)],
            ];
        }
        foreach (['sms_templates', 'message_templates'] as $key) {
            if ($request->has($key)) $rules += [
                $key.'.*' => 'required|array:id,name,active,event,text',
                $key.'.*.id' => ['required', 'distinct', \Illuminate\Validation\Rule::in($events)],
                $key.'.*.text' => 'required|string|max:2000', $key.'.*.active' => 'required|boolean',
                $key.'.*.name' => 'required|string|max:100', $key.'.*.event' => 'required|string|max:100',
            ];
        }
        $validated = $request->validate($rules);
        foreach ($validated['meta_templates'] ?? [] as $mapping) {
            if ($mapping['active'] && empty($mapping['name'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['meta_templates' => 'Active Meta mappings require an approved template name.']);
            }
        }

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

        $smsProvider = $payload['sms_provider'] ?? Settings::str('sms_provider');
        $smsEnabled = $payload['sms_enabled'] ?? Settings::bool('sms_enabled');
        if ($smsEnabled && array_intersect(array_keys($payload), ['sms_enabled','sms_provider','veevo_api_key','sendpk_api_key','sendpk_sender_id'])) {
            $key = $smsProvider.'_api_key';
            if (blank($payload[$key] ?? Settings::str($key))) {
                throw \Illuminate\Validation\ValidationException::withMessages([$key => 'The selected SMS provider requires an API key.']);
            }
            if ($smsProvider === 'sendpk' && blank($payload['sendpk_sender_id'] ?? Settings::str('sendpk_sender_id'))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['sendpk_sender_id' => 'SendPK requires an approved sender ID.']);
            }
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
        $result = WhatsAppService::testConnection();
        return response()->json(['success' => $result['ok']] + $result, $result['ok'] ? 200 : 422);
    }

    public function sendTest(Request $request): JsonResponse
    {
        return $this->testTemplate($request);
    }

    /** Verifies the configured SMS provider is reachable, without sending anything. */
    public function testSms(): JsonResponse
    {
        $result = SmsService::testConnection();

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'verified' => $result['verified'] ?? false,
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
                ? 'SMS accepted for sending. Delivery is not yet confirmed.'
                : ($result['error'] ?? 'Could not send the SMS.'),
        ], $result['sent'] ? 200 : 422);
    }

    /** Fetch SMS balance / package information from the active provider. */
    public function smsBalance(): JsonResponse
    {
        $result = SmsService::balance();

        return response()->json([
            'success' => $result['ok'],
            'data' => ['balance' => $result['balance'] ?? null],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Sends one template to a nominated number so the shop can see exactly what
     * lands, using the same code path as a real notification.
     */
    public function testTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', \Illuminate\Validation\Rule::in(array_column(Settings::defaultTemplates(), 'id'))],
            'phone' => ['required', 'string', 'max:50'],
        ]);
        $order = Order::with('customer')->latest()->first();
        $variables = $order ? \App\Services\NotificationVariables::variablesForOrder($order) : \App\Services\NotificationVariables::shopVariables();
        $result = WhatsAppService::sendMapped($validated['template_id'], $validated['phone'], $variables);
        return response()->json(['success' => $result['sent'], 'message' => $result['sent']
            ? 'Meta accepted the approved template for sending. Delivery is not yet confirmed.' : $result['error'],
            'result' => $result], $result['sent'] ? 200 : 422);
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
