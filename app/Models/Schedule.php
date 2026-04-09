<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedules';

    public $timestamps = true;

    protected $fillable = [
        'type',
        'start_date',
        'end_date',
        'signup_deadline',
        'id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'signup_deadline' => 'datetime',
        ];
    }

    public static function getCurrent(): ?self
    {
        return self::latest('id')->first();
    }

    public function isActive(): bool
    {
        return match ($this->type) {
            'disabled' => false,
            'always' => true,
            'signup' => $this->signup_deadline !== null && now()->lte($this->signup_deadline),
            default => false,
        };
    }

    public function isDateInSchedule(Carbon $date): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return match ($this->type) {
            'always' => true,
            'signup' => $this->start_date !== null
                && $this->end_date !== null
                && $date->gte($this->start_date->copy()->startOfDay())
                && $date->lte($this->end_date->copy()->endOfDay()),
            default => false,
        };
    }

    public function relativeWeekLabel(): ?string
    {
        if ($this->type !== 'signup' || $this->start_date === null) {
            return null;
        }

        $startWeek = $this->start_date->copy()->startOfWeek();
        $currentWeek = now()->startOfWeek();

        if ($startWeek->eq($currentWeek)) {
            return 'bieżący tydzień';
        }

        if ($startWeek->eq($currentWeek->copy()->addWeek())) {
            return 'następny tydzień';
        }

        return null;
    }

    public function toStatusArray(): array
    {
        if (!$this->isActive()) {
            return ['is_active' => false, 'text' => ''];
        }

        if ($this->type === 'always') {
            return [
                'is_active' => true,
                'text' => 'Grafik: <strong>Aktywny</strong>',
            ];
        }

        if ($this->type === 'signup') {
            $deadline = e($this->signup_deadline->format('d.m.Y H:i'));
            $rangeStart = e($this->start_date->format('d.m.Y'));
            $rangeEnd = e($this->end_date->format('d.m.Y'));
            $label = $this->relativeWeekLabel();
            $suffix = $label !== null ? ' (' . e($label) . ')' : '';

            return [
                'is_active' => true,
                'text' => "Grafik aktywny do: <strong>{$deadline}</strong><span><br/>Możliwość zapisu w zakresie<br/><strong>{$rangeStart} – {$rangeEnd}</strong>{$suffix}</span>",
            ];
        }

        return ['is_active' => false, 'text' => ''];
    }
}
