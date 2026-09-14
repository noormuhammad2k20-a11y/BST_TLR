<?php
namespace App\Http\Controllers;

use App\Models\{Delivery,Order,SmsLog};
use App\Services\{CollectionBoard,CollectionNotifications,OrderService,StatsService};
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        app(CollectionNotifications::class)->reconcile();
        $data=app(CollectionBoard::class)->data();
        if ($request->boolean('json') || $request->expectsJson()) return response()->json($data);
        return view('delivery.index',$data+['statuses'=>CollectionNotifications::STATUSES]);
    }

    public function bulkNotify(Request $request): JsonResponse
    {
        $data=$request->validate([
            'order_ids'=>['required_without:delivery_ids','array','min:1','max:200'],
            'order_ids.*'=>['integer','distinct','exists:orders,id'],
            'delivery_ids'=>['required_without:order_ids','array','min:1','max:200'],
            'delivery_ids.*'=>['integer','distinct','exists:deliveries,id'],
        ]);
        $ids=$data['order_ids'] ?? Delivery::whereIn('id',$data['delivery_ids'])->pluck('order_id')->all();
        return response()->json(app(CollectionNotifications::class)->sendOrders($ids));
    }

    public function history(Request $request): JsonResponse
    {
        $data=$request->validate(['order_id'=>['nullable','integer','exists:orders,id'],'filter'=>['nullable',Rule::in(['today','failed'])]]);
        $logs=SmsLog::whereNotNull('reason')->with(['collectionOrders:id,order_number','customer:id,name'])
            ->addSelect(['sms_logs.*','attempt_count'=>SmsLog::selectRaw('count(*)')->from('sms_logs as attempts')
                ->whereColumn('attempts.customer_id','sms_logs.customer_id')->whereColumn('attempts.id','<=','sms_logs.id')->whereNotNull('attempts.reason')])
            ->when($data['order_id'] ?? null,fn ($q,$id)=>$q->whereHas('collectionOrders',fn ($o)=>$o->where('orders.id',$id)))
            ->when(($data['filter'] ?? null)==='today',fn ($q)=>$q->whereDate('sent_at',now()->toDateString())->whereIn('status',CollectionNotifications::SUCCESS))
            ->when(($data['filter'] ?? null)==='failed',fn ($q)=>$q->whereIn('status',['failed','unknown','sending']))
            ->latest('id')->paginate(40);
        return response()->json($logs);
    }

    public function resolveSms(Request $request, SmsLog $sms): JsonResponse
    {
        $data=$request->validate(['outcome'=>['required',Rule::in(['accepted','failed'])],
            'confirmation'=>['required',Rule::in(['CHECKED'])],'provider_reference'=>['required_if:outcome,accepted','nullable','string','max:255']]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($sms,$data) {
            $log=SmsLog::lockForUpdate()->findOrFail($sms->id);
            abort_unless($log->reason && in_array($log->status,['unknown','sending'],true),422,'Only uncertain attempts can be reconciled.');
            abort_if($log->created_at->gt(now()->subMinutes(2)),422,'The SMS request is still in progress. Check again after two minutes.');
            $log->update(['status'=>$data['outcome'],'sent_at'=>$data['outcome']==='accepted' ? $log->created_at : null,
                'provider_message_id'=>$data['provider_reference'] ?? null,'error'=>$data['outcome']==='failed' ? 'Operator checked provider: not sent. Retry allowed.' : null,
                'api_response'=>json_encode(['reconciled_by'=>auth()->id(),'reconciled_at'=>now()->toIso8601String()])]);
            if ($data['outcome'] === 'failed') {
                // Clear only this attempt's stale order badge, not a newer attempt.
                $log->collectionOrders()->where('ready_sms_attempt_id', $log->attempt_key)
                    ->update(['ready_sms_state'=>'failed']);
            }
            app(CollectionNotifications::class)->finalize($log);
        });
        StatsService::flush();
        return response()->json(['success'=>true,'message'=>'Provider outcome recorded.']);
    }

    public function collect(Request $request, Order $order): JsonResponse
    {
        $data=$request->validate(['amount'=>'nullable|numeric|min:0|decimal:0,2',
            'payment_method'=>['required_with:amount',Rule::in(\App\Models\Payment::METHODS)],
            'operation_key'=>'required_with:amount|nullable|uuid']);
        $updated=\Illuminate\Support\Facades\DB::transaction(function() use($order,$data) {
            \App\Models\Customer::whereKey($order->customer_id)->lockForUpdate()->firstOrFail();
            $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((float)($data['amount']??0)>0) app(\App\Services\CustomerLedger::class)->receive($locked->customer,$data);
            return app(OrderService::class)->changeStatus($locked,'Delivered','Collected by customer');
        },3);
        return response()->json(['success'=>true,'message'=>'Collection recorded.','order_id'=>$updated->id,
            'dues'=>app(\App\Services\CustomerLedger::class)->orderDues($updated),
            'receipt_url'=>\App\Services\Settings::bool('delivery_print_receipt')
                ? route('delivery.receipt', $updated) : null]);
    }

    public function receipt(Order $order)
    {
        abort_unless($order->status === 'Delivered', 422, 'Final receipts are available after collection.');
        $payload = app(OrderController::class)->receipt($order)->getData(true);
        return response()->view('delivery.receipt', [
            'receipt' => $payload['receipt'], 'order' => $order, 'dues' => $payload['dues'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate(['status'=>['required',Rule::in(['Delivered'])],'note'=>['nullable','string','max:255']]);
        return $this->collect($request, Order::findOrFail($delivery->order_id));
    }

    public function destroy(Delivery $delivery): JsonResponse
    {
        return response()->json(['message'=>'Collection records are retained with their order history.'],422);
    }
}

