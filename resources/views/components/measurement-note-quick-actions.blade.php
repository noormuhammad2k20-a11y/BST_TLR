@props(['targetId' => 'meas-notes'])
@php
$quickActions = [
    'Goll Daman xx',
    'Sherwani Goll 1',
    'Gheer Full Bara',
    'Double Kantii',
    'Chain pocket',
    'Chakor Daman xx',
    'Sherwani Chakor 1',
    'Goll Daman 1+1',
    'Coller Nok 2.50',
    'Goll Daman 1+2',
    'Coller Nok 2.25',
    'Collar Nok 2',
    'Collar Nok 1.75',
    'Collar Nok 1.50',
    'Collar Nok 2.75',
    'Collar Nok 3',
    'Sherwani Goll 0.75',
    'Sherwan Chakor 0.75',
    'SHerwani Chakor 0.50',
    'SHerwani Goll 0.50',
];
@endphp
<div class="mt-3 border border-slate-200 bg-slate-50/60 rounded-xl p-3 sm:p-4">
    <div class="flex items-center gap-2 mb-2.5">
        <span class="text-xs font-bold text-slate-700">Quick Actions</span>
        <span class="text-[10px] font-medium text-slate-400">&bull; Tap to add &bull; repeat allowed</span>
    </div>
    <div class="flex flex-wrap gap-2">
@foreach($quickActions as $action)
        <button type="button" onclick="appendMeasurementQuickAction('{{ $targetId }}', '{{ addslashes($action) }}')" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 transition-colors active:bg-indigo-100 shadow-sm">{{ $action }}</button>
@endforeach
    </div>
</div>
