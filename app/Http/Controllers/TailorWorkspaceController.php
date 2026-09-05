<?php
namespace App\Http\Controllers;

use App\Models\{Order,Measurement};
use App\Services\OrderService;
use Illuminate\Http\Request;
use Closure;

final class TailorWorkspaceController extends Controller
{
    public function dispatchAuthorized(Request $request,Closure $next)
    {
        $name=$request->route()->getName(); $user=$request->user();
        $jobs=Order::where('tailor_id',$user->id);
        if (in_array($name,['dashboard','orders.index'])) {
            return response()->view('orders.tailor',['jobs'=>$jobs->with('customer:id,name')->latest()->paginate(25)]);
        }
        if (in_array($name,['orders.show','orders.status'])) {
            $bound=$request->route('order'); $order=(clone $jobs)->whereKey($bound instanceof Order?$bound->id:$bound)->first();
            abort_unless($order,403);
            if ($name==='orders.status') {
                $data=$request->validate(['status'=>'required|in:In Progress,Ready for Verification','note'=>'nullable|string|max:255']);
                app(OrderService::class)->changeStatus($order,$data['status'],$data['note']??null);
            }
            return response()->json(['success'=>true,'order'=>$order->only(['id','order_number','garment','status','delivery_date'])]);
        }
        if (in_array($name,['measurements.index','measurements.show','measurements.update'])) {
            $ids=(clone $jobs)->pluck('id');
            $measurements=Measurement::where(function($q) use ($ids,$jobs) {
                $q->whereIn('order_id',$ids)->orWhereIn('id',(clone $jobs)->whereNotNull('measurement_id')->select('measurement_id'));
            });
            if ($name==='measurements.index') {
                return response()->view('orders.tailor',['jobs'=>$jobs->with('customer:id,name')->latest()->paginate(25)]);
            }
            $bound=$request->route('measurement');
            $measurement=$measurements->whereKey($bound instanceof Measurement?$bound->id:$bound)->first();
            abort_unless($measurement,403);
            // The existing editor remains available, but assignment/customer identity cannot be changed.
            if ($request->isMethod('put') || $request->isMethod('patch')) {
                $request->merge(['customer_id'=>$measurement->customer_id,'tailor'=>$user->name]);
            }
            return $next($request);
        }
        if (in_array($name,['live.counters','live.notifications'])) return response()->json([]);
        abort(403,'Only assigned tailoring jobs and measurements are available to this account.');
    }
}
