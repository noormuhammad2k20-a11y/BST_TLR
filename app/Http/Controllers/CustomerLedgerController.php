<?php
namespace App\Http\Controllers;
use App\Models\{Customer,Payment};
use App\Services\{CustomerLedger,Decimal,ActivityLogger,StatsService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerLedgerController extends Controller
{
    public function show(Request $request,Customer $customer,CustomerLedger $ledger) {
        $statement=$ledger->statement($customer);
        if ($request->expectsJson()) return response()->json($statement);
        return view('customers.ledger',compact('customer','statement'));
    }
    public function receive(Request $request,Customer $customer,CustomerLedger $ledger) {
        $data=$request->validate(['amount'=>'required|numeric|min:0.01|max:999999999999.99|decimal:0,2',
            'payment_method'=>['required',Rule::in(Payment::METHODS)],'date'=>'required|date|before_or_equal:now',
            'notes'=>'nullable|string|max:1000','operation_key'=>'required|uuid']);
        return response()->json(['success'=>true,'statement'=>$ledger->receive($customer,$data)]);
    }
    public function charge(Request $request,Customer $customer) {
        $data=$request->validate(['type'=>['required',Rule::in(['Cloth Sale','Opening Due'])],
            'description'=>'required|string|max:255','amount'=>'required|numeric|min:0.01|max:999999999999.99|decimal:0,2',
            'date'=>'required|date|before_or_equal:now','operation_key'=>'required|uuid']);
        DB::transaction(function() use($customer,$data) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $existing=DB::table('customer_ledger_charges')->where('operation_key',$data['operation_key'])->first();
            if ($existing) {
                abort_unless($existing->customer_id===$customer->id && Decimal::cmp($existing->amount,(string)$data['amount'])===0,422,'Reference already used.');
                return;
            }
            DB::table('customer_ledger_charges')->insert($data+['customer_id'=>$customer->id,'recorded_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            ActivityLogger::log('Ledger charge added',$data['description'],'payments',$customer,['amount'=>$data['amount']],'created');
        });
        StatsService::flush();
        return response()->json(['success'=>true]);
    }
}
