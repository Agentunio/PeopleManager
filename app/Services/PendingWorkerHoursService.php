<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PendingWorkerHoursService
{
    private const PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, array{name: string, date: string, shift: string}>
     */
    public function paginate(int $page = 1): LengthAwarePaginator
    {
        $shiftPaginator = $this->query()
            ->with('worker:id,first_name,last_name')
            ->orderBy('day')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'worker_id', 'day', 'shift_type'], 'page', $page);

        /** @var Collection<int, WorkerShift> $shifts */
        $shifts = $shiftPaginator->getCollection();
        $items = $shifts->map(function (WorkerShift $shift): array {
            $worker = $shift->getRelation('worker');

            return [
                'name' => $worker instanceof Worker
                    ? trim($worker->first_name.' '.$worker->last_name)
                    : '',
                'date' => Carbon::parse($shift->day)->translatedFormat('d.m.Y'),
                'shift' => $shift->shift_type === 'morning' ? 'Poranna' : 'Popołudniowa',
            ];
        });

        return new LengthAwarePaginator(
            $items,
            $shiftPaginator->total(),
            $shiftPaginator->perPage(),
            $shiftPaginator->currentPage(),
            [
                'path' => $shiftPaginator->path(),
                'pageName' => $shiftPaginator->getPageName(),
            ],
        );
    }

    private function query(): Builder
    {
        return WorkerShift::query()
            ->published()
            ->where('hours_source', 'worker')
            ->whereNull('substituted_for_shift_id')
            ->whereNotNull('worker_from_time');
    }
}
