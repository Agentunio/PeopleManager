<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageStoreRequest;
use App\Http\Requests\PackageUpdateRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $packages = Package::orderBy('name')->get();

        return view('system.settings', [
            'packages' => $packages,
        ]);
    }

    public function store(PackageStoreRequest $request): RedirectResponse
    {
        Package::create($request->validated());

        return back()->with('success', 'Pakiet został dodany pomyślnie.');
    }

    public function update(PackageUpdateRequest $request, Package $package): RedirectResponse
    {
        $package->update($request->validated());

        return back()->with('success', 'Pakiet został zaktualizowany.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $name = $package->name;
        $package->delete();

        return back()->with('success', "Pakiet {$name} został usunięty.");
    }
}
