<div class="relative pl-6">
  <div class="absolute left-[7px] top-2 bottom-2 w-0.5 bg-slate-200"></div>

  @forelse($activities as $activity)
  @php
    $dotColor = match($activity->category) {
      'orders'    => 'border-indigo-600',
      'payments'  => 'border-emerald-500',
      'customers' => 'border-sky-500',
      'inventory' => 'border-amber-500',
      'auth'      => 'border-purple-500',
      default     => 'border-slate-400',
    };
  @endphp
  <!-- Activity Item -->
  <div class="relative {{ !$loop->last ? 'pb-6' : '' }}">
    <div class="absolute -left-[18px] top-1.5 w-3 h-3 rounded-full bg-white border-2 {{ $dotColor }}"></div>
    <div class="text-xs text-slate-500 font-medium">{{ $activity->created_at->diffForHumans() }} · {{ $activity->created_at->format('d M, g:i A') }}</div>
    <div class="text-sm font-medium text-slate-900 mt-0.5">{{ $activity->action }}</div>
    <div class="text-xs text-slate-500 mt-0.5">{{ $activity->description }} · <span class="text-indigo-600">By {{ $activity->actor_label }}</span></div>
  </div>
  @empty
  <div class="py-10 text-center">
    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-clock-rotate-left text-base"></i></div>
    <div class="text-sm font-semibold text-slate-700">{{ $search || $category !== 'all' ? 'No matching activity' : 'No activity recorded yet' }}</div>
    <div class="text-xs text-slate-500 mt-1">{{ $search || $category !== 'all' ? 'Try a different filter or search term.' : 'Actions across the system are logged here automatically.' }}</div>
  </div>
  @endforelse
</div>

@if($activities->hasPages())
<div class="mt-6 pt-4 border-t border-slate-200">
  {{ $activities->links() }}
</div>
@endif
