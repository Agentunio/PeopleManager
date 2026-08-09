<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlannerAvailableStoreRequest;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class PlannerAvailableController extends Controller
{
    public function store(PlannerAvailableStoreRequest $request): RedirectResponse
    {
        /** @var array{type: 'signup'|'always'|'admin'|'disabled', start_date?: string, end_date?: string, signup_deadline?: string} $data */
        $data = $request->validated();

        Schedule::updateOrCreate(['id' => 1], $data);

        $message = match ($data['type']) {
            'signup' => sprintf(
                'Grafik aktywny do %s, zakres dni: %s – %s',
                Carbon::parse($data['signup_deadline'])->format('d.m.Y H:i'),
                Carbon::parse($data['start_date'])->format('d.m.Y'),
                Carbon::parse($data['end_date'])->format('d.m.Y'),
            ),
            'always' => $this->alwaysMessage($data),
            'admin' => sprintf(
                'Grafik tylko dla administratora, zakres dni: %s – %s',
                Carbon::parse($data['start_date'])->format('d.m.Y'),
                Carbon::parse($data['end_date'])->format('d.m.Y'),
            ),
            'disabled' => 'Grafik nie jest już aktywny',
        };

        return back()->with('success', $message);
    }

    private function alwaysMessage(array $data): string
    {
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return 'Grafik będzie aktywny do jego wyłączenia';
        }

        return sprintf(
            'Zapisy bez terminu, zakres dni: %s – %s',
            Carbon::parse($data['start_date'])->format('d.m.Y'),
            Carbon::parse($data['end_date'])->format('d.m.Y'),
        );
    }
}
