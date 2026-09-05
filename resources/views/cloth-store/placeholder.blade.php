@extends('cloth-store.layouts.app')

@section('title', $title ?? 'Cloth Store')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 md:p-10 text-center">
        <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="{{ $icon ?? 'fa-solid fa-store' }} text-2xl"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-900 mb-2">{{ $title ?? 'Cloth Store Module' }}</h2>
        <p class="text-slate-500 max-w-md mx-auto">
            This module is part of the new Cloth Store Management System. It shares the same premium design language as the Tailor system but operates entirely independently.
        </p>
    </div>
</div>
@endsection
