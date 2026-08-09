<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $type
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $signup_deadline
 */
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
        return self::query()->find(1);
    }

    public function isActive(): bool
    {
        return match ($this->type) {
            'disabled', 'admin' => false,
            'always' => $this->end_date === null || now()->startOfDay()->lte($this->end_date->copy()->endOfDay()),
            'signup' => $this->hasCompleteRange()
                && $this->signup_deadline !== null
                && now()->lte($this->signup_deadline),
            default => false,
        };
    }

    public function isAdminManaged(): bool
    {
        return $this->type === 'admin';
    }

    public function isDateInSchedule(Carbon $date): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return match ($this->type) {
            'always' => $this->isWithinOptionalRange($date),
            'signup' => $this->hasCompleteRange() && $this->isWithinRange($date),
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
        if (! $this->isActive()) {
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
            $suffix = $label !== null ? ' ('.e($label).')' : '';

            return [
                'is_active' => true,
                'text' => "Grafik aktywny do: <strong>{$deadline}</strong><span><br/>Możliwość zapisu w zakresie<br/><strong>{$rangeStart} – {$rangeEnd}</strong>{$suffix}</span>",
            ];
        }

        return ['is_active' => false, 'text' => ''];
    }

    public function toAdminWindowArray(): array
    {
        $hasRange = $this->hasCompleteRange();

        return [
            'type' => $this->type,
            'is_configured' => $this->type !== 'disabled',
            'allows_signup' => $this->isActive(),
            'is_admin_managed' => $this->isAdminManaged(),
            'range_label' => $hasRange
                ? $this->start_date->format('d.m.Y').' – '.$this->end_date->format('d.m.Y')
                : null,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'deadline' => $this->signup_deadline?->format('d.m.Y H:i'),
            'deadline_input' => $this->signup_deadline?->format('Y-m-d\TH:i'),
            'days_left' => $this->type === 'signup' ? $this->signupDaysLeft() : null,
        ];
    }

    public static function emptyAdminWindow(): array
    {
        return [
            'type' => 'disabled',
            'is_configured' => false,
            'allows_signup' => false,
            'is_admin_managed' => false,
            'range_label' => null,
            'start_date' => null,
            'end_date' => null,
            'deadline' => null,
            'deadline_input' => null,
            'days_left' => null,
        ];
    }

    /**
     * Structured sign-up data for the worker dashboard box.
     *
     * Every date is guarded: signup_deadline/start_date/end_date are nullable in
     * the DB (only PlannerAvailableStoreRequest enforces them), and a format()
     * call on null would be a fatal error on the worker's landing page.
     * Type 'always' is active but carries no deadline or range.
     */
    public function toSignupArray(): array
    {
        if (! $this->isActive()) {
            return ['is_active' => false];
        }

        if ($this->type !== 'signup') {
            return [
                'is_active' => true,
                'deadline' => null,
                'range_start' => $this->start_date?->format('d.m'),
                'range_end' => $this->end_date?->format('d.m'),
                'relative_label' => null,
                'days_left' => null,
            ];
        }

        return [
            'is_active' => true,
            'deadline' => $this->signup_deadline?->format('d.m, H:i'),
            'range_start' => $this->start_date?->format('d.m'),
            'range_end' => $this->end_date?->format('d.m'),
            'relative_label' => $this->relativeWeekLabel(),
            'days_left' => $this->signupDaysLeft(),
        ];
    }

    private function hasCompleteRange(): bool
    {
        return $this->start_date !== null && $this->end_date !== null;
    }

    private function isWithinOptionalRange(Carbon $date): bool
    {
        return ! $this->hasCompleteRange() || $this->isWithinRange($date);
    }

    private function isWithinRange(Carbon $date): bool
    {
        return $date->gte($this->start_date->copy()->startOfDay())
            && $date->lte($this->end_date->copy()->endOfDay());
    }

    /**
     * Whole days left until the sign-up deadline, compared date-to-date so a
     * few hours' difference cannot shift the count by one.
     */
    private function signupDaysLeft(): ?int
    {
        if ($this->signup_deadline === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            $this->signup_deadline->copy()->startOfDay(),
            false
        );
    }
}
