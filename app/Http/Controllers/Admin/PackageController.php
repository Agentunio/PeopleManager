<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageSetDefaultRequest;
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
        $defaultPackageId = $packages->firstWhere('is_default', true)?->id;

        return view('admin.settings.index', [
            'packages' => $packages,
            'defaultPackageId' => $defaultPackageId,
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

    public function setDefault(PackageSetDefaultRequest $request): RedirectResponse
    {
        Package::setAsDefault($request->input('package_id'));

        return back()->with('success', 'Domyślna stawka została zaktualizowana.');
    }
}
