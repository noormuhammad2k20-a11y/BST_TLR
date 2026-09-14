<?php

namespace App\Http\Controllers;

use App\Services\SidebarAppearance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SidebarAppearanceController extends Controller
{
    public function show(SidebarAppearance $appearance)
    {
        return response()->json($appearance->current())->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, SidebarAppearance $appearance)
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark'])],
            'color' => ['required', Rule::in(array_keys(config('sidebar.palettes')))],
        ]);

        return response()->json($appearance->save($validated))->header('Cache-Control', 'no-store, private');
    }
}
