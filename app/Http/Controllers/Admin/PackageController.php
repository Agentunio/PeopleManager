<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageStoreRequest;
use App\Http\Requests\Admin\PackageUpdateRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $packages = Package::orderBy('name')->get();

        return view('admin.settings.index', [
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
