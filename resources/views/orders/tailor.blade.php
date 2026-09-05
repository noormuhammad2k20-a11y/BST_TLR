@extends('layouts.app')
@section('title', 'Assigned Orders')
@section('content')
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
  <div class="p-6 border-b border-slate-100"><h1 class="text-lg font-semibold text-slate-900">Assigned Orders</h1></div>
  <table class="w-full text-sm text-left">
    <thead class="bg-slate-50 text-slate-500"><tr><th class="px-6 py-3">Order</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Garment</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Action</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
    @foreach($jobs as $job)
      <tr><td class="px-6 py-4">{{ $job->display_number }}</td><td class="px-6 py-4">{{ $job->customer?->name }}</td><td class="px-6 py-4">{{ $job->garment }}</td><td class="px-6 py-4">{{ $job->status }}</td>
      <td class="px-6 py-4">
        @if(in_array($job->status,['Pending','In Progress']))
        <form method="post" action="{{ route('orders.status',$job) }}">@csrf @method('PATCH')
          <input type="hidden" name="status" value="{{ $job->status==='Pending'?'In Progress':'Ready for Verification' }}">
          <button class="px-3 py-2 bg-slate-900 text-white rounded-lg" type="submit">{{ $job->status==='Pending'?'Start work':'Submit for verification' }}</button>
        </form>
        @endif
      </td></tr>
    @endforeach
    </tbody>
  </table>
  <div class="p-4">{{ $jobs->links() }}</div>
</div>
@endsection
