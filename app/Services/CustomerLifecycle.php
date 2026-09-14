<?php

namespace App\Services;

use App\Models\{Customer, Order, Payment};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerLifecycle
{
    public static function phoneKey(?string $phone): string
    {
        $value = trim((string)$phone);
        // Keep import placeholders distinct; normalize actual phone numbers only.
        return preg_match('/^\+?[\d\s()\-]+$/D', $value)
            ? CustomerImporter::phoneKey($value) : mb_strtolower($value);
    }

    public static function matchingPhone(string $phone, ?int $except = null): ?Customer
    {
        $key = self::phoneKey($phone);
        return Customer::withTrashed()->whereNull('anonymized_at')
            ->when($except, fn ($q) => $q->where('id','<>',$except))
            ->where(fn ($q) => $q->where('phone_key',$key)->orWhereNull('phone_key'))
            ->orderByRaw('deleted_at IS NOT NULL')->orderBy('id')->get()
            ->first(fn ($c) => self::phoneKey($c->phone) === $key);
    }

    public static function rejectDuplicate(Customer $existing): never
    {
        if ($existing->trashed()) {
            $message = 'This phone number belongs to an archived customer. Restore Customer to use the existing record.';
            throw new HttpResponseException(response()->json([
                'message'=>$message, 'errors'=>['phone'=>[$message]],
                'archived_customer'=>['db_id'=>$existing->id,'name'=>$existing->name],
            ],409));
        }
        throw ValidationException::withMessages(['phone'=>'A customer with this phone number already exists.']);
    }

    public function archive(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $record = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            if ($record->trashed()) return;
            $record->delete();
            ActivityLogger::log('Archived Customer', 'Customer archived; history retained.', 'customers', $record, [], 'archived');
        });
    }

    public function restore(int $id): Customer
    {
        return DB::transaction(function () use ($id) {
            $customer = Customer::onlyTrashed()->whereNull('anonymized_at')->lockForUpdate()->findOrFail($id);
            if ($existing = self::matchingPhone($customer->phone, $id)) self::rejectDuplicate($existing);
            $customer->restore();
            ActivityLogger::log('Restored Customer', 'Customer restored to the directory.', 'customers', $customer, [], 'restored');
            return $customer;
        });
    }

    public function hasHistory(Customer $customer): bool
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('customer_ledger_charges') && DB::table('customer_ledger_charges')->where('customer_id',$customer->id)->exists()) return true;
        // Raw queries deliberately include archived orders and all payment statuses.
        foreach (['orders','payments','measurements','notifications','sms_logs','whats_app_logs'] as $table) {
            if (DB::table($table)->where('customer_id',$customer->id)->exists()) return true;
        }
        return DB::table('integrity_audits')->where('source_table','customers')->where('source_id',$customer->id)->exists();
    }

    public function permanentlyDelete(int $id, string $confirmation): string
    {
        if ($confirmation !== 'DELETE') throw ValidationException::withMessages(['confirmation'=>'Type DELETE exactly to confirm permanent deletion.']);
        return DB::transaction(function () use ($id) {
            $customer = Customer::onlyTrashed()->whereNull('anonymized_at')->lockForUpdate()->findOrFail($id);
            $history = $this->hasHistory($customer);
            $this->scrubPersonalCopies($customer);
            if ($history) {
                $customer->forceFill([
                    'name'=>'Deleted Customer '.$customer->display_code, 'phone'=>'Removed', 'phone_key'=>null,
                    'email'=>null,'city'=>null,'address'=>null,'notes'=>null,'behavior'=>null,
                    'loyalty_score'=>null,'last_visit_at'=>null,'type'=>'Regular','is_active'=>false,'anonymized_at'=>now(),
                ])->save();
            } else {
                $customer->forceDelete();
            }
            ActivityLogger::log('Permanently Deleted Customer', $history ? 'Customer anonymized; business history retained.' : 'Customer without business history permanently deleted.',
                'customers', null, ['customer_id'=>$id,'anonymized'=>$history], 'deleted');
            return $history ? 'anonymized' : 'deleted';
        });
    }

    private function scrubPersonalCopies(Customer $customer): void
    {
        $orderIds = DB::table('orders')->where('customer_id',$customer->id)->pluck('id');
        $paymentIds = DB::table('payments')->where(function ($q) use ($customer,$orderIds) {
            $q->where('customer_id',$customer->id)->orWhereIn('order_id',$orderIds);
        })->pluck('id');
        // Preserve IDs, financial amounts, invoice numbers, statuses and timestamps.
        DB::table('deliveries')->whereIn('order_id',$orderIds)->update(['address'=>null,'recipient_name'=>null,'notes'=>null]);
        DB::table('orders')->whereIn('id',$orderIds)->update(['notes'=>null,'style_notes'=>null]);
        DB::table('payments')->whereIn('id',$paymentIds)->update(['notes'=>null]);
        DB::table('measurements')->where('customer_id',$customer->id)->update(['notes'=>null,'template_name'=>null]);
        DB::table('notifications')->where(function ($q) use ($customer,$orderIds) {
            $q->where('customer_id',$customer->id)->orWhereIn('order_id',$orderIds);
        })->update(['title'=>'Customer removed','message'=>'Personal customer data removed.']);
        foreach (['sms_logs','whats_app_logs'] as $table) {
            DB::table($table)->where(function ($q) use ($customer,$orderIds,$table) {
                $q->where('customer_id',$customer->id)->orWhereIn('order_id',$orderIds);
                if ($table === 'sms_logs' && \Illuminate\Support\Facades\Schema::hasTable('collection_sms_orders')) {
                    $q->orWhereIn('id',DB::table('collection_sms_orders')->whereIn('order_id',$orderIds)->select('sms_log_id'));
                }
            })->update(['phone'=>'Removed','message'=>'Personal customer data removed.','error'=>null,'api_response'=>null]);
        }
        $activities = DB::table('activity_logs')->where(function ($q) use ($customer,$orderIds,$paymentIds) {
            $q->where(fn ($s) => $s->where('subject_type',Customer::class)->where('subject_id',$customer->id))
                ->orWhere(fn ($s) => $s->where('subject_type',Order::class)->whereIn('subject_id',$orderIds))
                ->orWhere(fn ($s) => $s->where('subject_type',Payment::class)->whereIn('subject_id',$paymentIds))
                // The legacy delete action stored only this description, without a subject ID.
                ->orWhere(fn ($s) => $s->where('category','customers')->whereNull('subject_id')->where('description',$customer->name.' was removed'));
        })->get(['id','description','properties']);
        foreach ($activities as $activity) {
            DB::table('activity_logs')->where('id',$activity->id)->update([
                'description'=>$this->redactPersonalValue($activity->description, $customer),
                'properties'=>$activity->properties === null ? null
                    : json_encode($this->redactPersonalValue(json_decode($activity->properties,true),$customer)),
            ]);
        }
        DB::table('integrity_audits')->where('source_table','customers')->where('source_id',$customer->id)
            ->update(['evidence'=>json_encode(['customer_id'=>$customer->id,'personal_data_removed'=>true])]);
    }

    private function redactPersonalValue(mixed $value, Customer $customer): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $value[$key] = is_string($key) && preg_match('/(?:^|_)(?:name|phone|email|address|city|notes)$/i',$key)
                    ? null : $this->redactPersonalValue($child,$customer);
            }
            return $value;
        }
        if (!is_string($value)) return $value;
        $personal = array_filter($customer->only(['name','phone','email','address','city']), fn ($v) => is_string($v) && $v !== '');
        return str_replace(array_values($personal), '[removed]', $value);
    }
}
