<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $workers = Worker::orderBy('last_name')
            ->take(5)
            ->get();

        return view('system.dashboard', [
            'workers' => $workers,
        ]);
    }
}
