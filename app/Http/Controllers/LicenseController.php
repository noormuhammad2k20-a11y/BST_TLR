<?php

namespace App\Http\Controllers;

use App\Services\Licensing\LicenseChecker;
use App\Services\Licensing\LicenseInstaller;
use App\Services\Licensing\MachineIdentity;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LicenseController extends Controller
{
    public function show(LicenseChecker $checker, MachineIdentity $machine)
    {
        try {
            $machineId = $machine->id();
        } catch (Throwable) {
            $machineId = null;
        }

        return response()->view('license.activate', ['result' => $checker->check(), 'machineId' => $machineId])
            ->header('Cache-Control', 'no-store, private');
    }

    public function install(Request $request, LicenseInstaller $installer)
    {
        $request->validate(['license' => ['required', 'file', 'max:16']]);
        $contents = file_get_contents($request->file('license')->getRealPath());
        try {
            $installer->install($contents);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['license' => $exception->getMessage()]);
        }

        return redirect()->route('license.show')->with('success', 'License installed successfully. This device is activated.');
    }
}
