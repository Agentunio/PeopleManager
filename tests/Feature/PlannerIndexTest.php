<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlannerIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'username' => 'index_admin',
            'email' => 'index-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    public function test_planner_index_contains_every_day_of_month_including_weekends(): void
    {
        $worker = Worker::create(['first_name' => 'Jan', 'last_name' => 'Niedzielny']);
        WorkerShift::create([
            'worker_id' => $worker->id,
            'day' => '2026-07-05',
            'shift_type' => 'morning',
            'status' => 'worked',
            'is_draft' => false,
        ]);

        $response = $this->actingAs($this->createAdmin())->get(route('planner.index', ['month' => '2026-07']));

        $response->assertOk()->assertViewHas('days', function (array $days): bool {
            $sunday = collect($days)->firstWhere('date', '2026-07-05');

            return count($days) === 31
                && $sunday !== null
                && $sunday['weekday_short'] === 'Nd'
                && $sunday['shifts']['morning']['people'][0]['name'] === 'Jan Niedzielny';
        });
    }

    public function test_planner_index_orders_substitute_after_absent_worker(): void
    {
        $date = '2026-07-06';
        $originalWorker = Worker::create(['first_name' => 'Anna', 'last_name' => 'Oryginalna']);
        $substituteWorker = Worker::create(['first_name' => 'Beata', 'last_name' => 'Zastepcza']);
        $original = WorkerShift::create([
            'worker_id' => $originalWorker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'absent',
            'minutes' => 0,
            'is_draft' => false,
        ]);
        WorkerShift::create([
            'worker_id' => $substituteWorker->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'substituted_for_shift_id' => $original->id,
            'is_draft' => false,
        ]);

        $response = $this->actingAs($this->createAdmin())->get(route('planner.index', ['month' => '2026-07']));

        $response->assertViewHas('days', function (array $days) use ($date): bool {
            $people = collect($days)->firstWhere('date', $date)['shifts']['morning']['people'];

            return array_column($people, 'state') === ['unavailable', 'substitute'];
        });
    }

    #[DataProvider('weekendDates')]
    public function test_default_signup_period_is_valid_on_weekend(
        string $now,
        string $expectedStart,
        string $expectedDeadline,
    ): void {
        Carbon::setTestNow($now);

        $response = $this->actingAs($this->createAdmin())->get(route('planner.index'));
        $form = $response->viewData('scheduleForm');

        $response->assertOk();
        $this->assertSame($expectedStart, $form['start_date']);
        $this->assertSame($expectedDeadline, $form['deadline']);
        $this->assertTrue(Carbon::parse($form['deadline'])->isFuture());
        $this->assertTrue(Carbon::parse($form['deadline'])->lt(Carbon::parse($form['start_date'])));
    }

    public static function weekendDates(): array
    {
        return [
            'Saturday' => ['2026-07-25 12:00:00', '2026-08-03', '2026-07-31T23:59'],
            'Sunday' => ['2026-07-26 12:00:00', '2026-08-03', '2026-07-31T23:59'],
        ];
    }

    public function test_worker_shifts_have_planner_query_indexes(): void
    {
        $indexNames = collect(Schema::getIndexes('worker_shifts'))->pluck('name');

        $this->assertTrue($indexNames->contains('worker_shifts_planner_day_shift_index'));
        $this->assertTrue($indexNames->contains('worker_shifts_planner_settlement_index'));
    }

    public function test_planner_index_renders_one_shared_person_action_menu(): void
    {
        $date = '2026-07-06';
        $firstWorker = Worker::create(['first_name' => 'Anna', 'last_name' => 'Pierwsza']);
        $secondWorker = Worker::create(['first_name' => 'Beata', 'last_name' => 'Druga']);

        foreach ([$firstWorker, $secondWorker] as $worker) {
            WorkerShift::create([
                'worker_id' => $worker->id,
                'day' => $date,
                'shift_type' => 'morning',
                'status' => 'worked',
                'is_draft' => false,
            ]);
        }

        $html = $this->actingAs($this->createAdmin())
            ->get(route('planner.index', ['month' => '2026-07']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match_all('/\sdata-person-menu(?:\s|=|>)/', $html));
        $this->assertSame(2, preg_match_all('/\sdata-person-menu-trigger(?:\s|=|>)/', $html));
        $this->assertStringContainsString('data-shift-status="absent"', $html);
        $this->assertStringContainsString('data-shift-status="worked"', $html);
        $this->assertStringContainsString('data-substitute-open', $html);
        $this->assertStringContainsString('data-shift-remove', $html);
    }

    public function test_planner_index_uses_shared_action_routes_instead_of_repeating_urls_per_worker(): void
    {
        $date = '2026-07-06';
        $firstShift = WorkerShift::create([
            'worker_id' => Worker::create(['first_name' => 'Anna', 'last_name' => 'Pierwsza'])->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'is_draft' => false,
        ]);
        $secondShift = WorkerShift::create([
            'worker_id' => Worker::create(['first_name' => 'Beata', 'last_name' => 'Druga'])->id,
            'day' => $date,
            'shift_type' => 'afternoon',
            'status' => 'worked',
            'is_draft' => false,
        ]);

        $html = $this->actingAs($this->createAdmin())
            ->get(route('planner.index', ['month' => '2026-07']))
            ->assertOk()
            ->getContent();

        foreach ([
            'data-shift-status-url-template',
            'data-substitute-candidates-url-template',
            'data-substitute-store-url-template',
            'data-shift-remove-url-template',
        ] as $attribute) {
            $this->assertSame(1, preg_match_all('/\\s'.preg_quote($attribute, '/').'=/', $html));
        }

        $this->assertSame(2, preg_match_all('/\\sdata-shift-id=/', $html));
        $this->assertStringContainsString('data-shift-id="'.$firstShift->id.'"', $html);
        $this->assertStringContainsString('data-shift-id="'.$secondShift->id.'"', $html);
        $this->assertStringNotContainsString(' data-status-url=', $html);
        $this->assertStringNotContainsString(' data-substitute-candidates-url=', $html);
        $this->assertStringNotContainsString(' data-substitute-store-url=', $html);
        $this->assertStringNotContainsString(' data-remove-url=', $html);
    }

    public function test_planner_keeps_all_month_actions_without_per_person_layout_wrappers(): void
    {
        $date = '2026-07-06';
        $shift = WorkerShift::create([
            'worker_id' => Worker::create([
                'first_name' => 'Anna',
                'last_name' => 'Lekka',
            ])->id,
            'day' => $date,
            'shift_type' => 'morning',
            'status' => 'worked',
            'is_draft' => false,
        ]);

        $html = $this->actingAs($this->createAdmin())
            ->get(route('planner.index', ['month' => '2026-07']))
            ->assertOk()
            ->getContent();

        $this->assertSame(31, preg_match_all('/\sdata-planner-day-card(?:\s|=|>)/', $html));
        $this->assertSame(62, preg_match_all('/class="planner-shift-column"/', $html));
        $this->assertSame(62, preg_match_all('/class="planner-add-worker"/', $html));
        $this->assertStringContainsString('data-shift-id="'.$shift->id.'"', $html);
        $this->assertStringNotContainsString('planner-person-wrap', $html);
    }
}
