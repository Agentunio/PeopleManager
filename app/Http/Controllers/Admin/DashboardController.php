<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $workers = Worker::orderBy('last_name')
            ->take(5)
            ->get();

        return view('dashboard.index', [
            'workers' => $workers,
        ]);
    }
}
