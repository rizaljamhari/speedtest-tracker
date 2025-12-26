<?php

namespace Tests\Feature;

use App\Actions\Speedtest\RunSpeedtest;
use App\Enums\ResultService;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MultiServiceSpeedtestTest extends TestCase
{
    public function test_it_dispatches_configured_services()
    {
        Bus::fake();

        GeneralSettings::fake([
            'ookla_enabled' => true,
            'fast_enabled' => true,
            'execution_mode' => 'parallel',
        ]);

        $action = app(RunSpeedtest::class);
        $results = $action->handle();

        $this->assertCount(2, $results);
        $this->assertEquals(ResultService::Ookla, $results[0]->service);
        $this->assertEquals(ResultService::Fast, $results[1]->service);

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'Ookla Speedtest';
        });

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'Fast Speedtest';
        });
    }

    public function test_it_runs_only_ookla_when_fast_disabled()
    {
        Bus::fake();

        GeneralSettings::fake([
            'ookla_enabled' => true,
            'fast_enabled' => false,
            'execution_mode' => 'parallel',
        ]);

        $action = app(RunSpeedtest::class);
        $results = $action->handle();

        $this->assertCount(1, $results);
        $this->assertEquals(ResultService::Ookla, $results[0]->service);

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'Ookla Speedtest';
        });

        Bus::assertNothingBatched(function ($batch) {
            return $batch->name === 'Fast Speedtest';
        });
    }

    public function test_it_dispatches_single_batch_for_sequential_mode()
    {
        Bus::fake();

        GeneralSettings::fake([
            'ookla_enabled' => true,
            'fast_enabled' => true,
            'execution_mode' => 'sequential',
        ]);

        $action = app(RunSpeedtest::class);
        $action->handle();

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'Sequential Speedtests';
        });

        Bus::assertNothingBatched(function ($batch) {
            return $batch->name === 'Ookla Speedtest';
        });
    }
}
