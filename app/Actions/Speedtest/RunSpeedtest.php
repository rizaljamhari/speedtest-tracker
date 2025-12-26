<?php

namespace App\Actions\Speedtest;

use App\Actions\Ookla\RunSpeedtest as LegacyOoklaAction; 
use App\Enums\ResultService;
use App\Enums\ResultStatus;
use App\Events\SpeedtestWaiting;
use App\Jobs\CheckForInternetConnectionJob;
use App\Jobs\Ookla\BenchmarkSpeedtestJob;
use App\Jobs\Ookla\CompleteSpeedtestJob;
use App\Jobs\Ookla\SelectSpeedtestServerJob;
use App\Jobs\Ookla\SkipSpeedtestJob;
use App\Jobs\Ookla\StartSpeedtestJob;
use App\Jobs\Speedtest\RunSpeedtestJob;
use App\Models\Result;
use App\Settings\GeneralSettings;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RunSpeedtest
{
    use AsAction;

    public function handle(bool $scheduled = false, ?int $serverId = null, ?int $dispatchedBy = null): array
    {
        $settings = app(GeneralSettings::class);
        $results = [];

        $services = [];

        if ($settings->ookla_enabled) {
            $services[] = ResultService::Ookla;
        }

        if ($settings->fast_enabled) {
            $services[] = ResultService::Fast;
        }

        if ($settings->cloudflare_enabled) {
            $services[] = ResultService::Cloudflare;
        }

        if (empty($services)) {
            Log::warning('No speedtest services enabled.');
            return [];
        }

        $servicePayloads = [];

        foreach ($services as $service) {
            $result = Result::create([
                'service' => $service,
                'status' => ResultStatus::Waiting,
                'scheduled' => $scheduled,
                'dispatched_by' => $dispatchedBy,
                'data->server->id' => ($service === ResultService::Ookla) ? ($serverId ?? $settings->speedtest_server) : null,
            ]);

            $results[] = $result;

            SpeedtestWaiting::dispatch($result);

            $jobs = [
                new StartSpeedtestJob($result),
                new CheckForInternetConnectionJob($result),
                new SkipSpeedtestJob($result),
            ];

            if ($service === ResultService::Ookla) {
                // Ookla specific jobs
                $jobs[] = new SelectSpeedtestServerJob($result);
            }

            // Generic execution job
            $jobs[] = new RunSpeedtestJob($result);

            // Generic/Ookla completion jobs (Reusing existing ones for now)
            $jobs[] = new BenchmarkSpeedtestJob($result);
            $jobs[] = new CompleteSpeedtestJob($result);

            $servicePayloads[] = [
                'service' => $service,
                'jobs' => $jobs,
            ];
        }

        if ($settings->execution_mode === 'parallel') {
            foreach ($servicePayloads as $payload) {
                /** @var \Illuminate\Bus\PendingBatch $batch */
                Bus::batch($payload['jobs'])
                    ->catch(function (Batch $batch, ?Throwable $e) {
                        Log::error(sprintf('Speedtest batch "%s" failed for an unknown reason.', $batch->id));
                    })
                    ->name(ucfirst($payload['service']->value) . ' Speedtest')
                    ->dispatch();
            }
        } else {
            // Sequential
            $chain = [];
            foreach ($servicePayloads as $payload) {
                $chain = array_merge($chain, $payload['jobs']);
            }

            Bus::batch($chain)
                ->name('Sequential Speedtests')
                ->catch(function (Batch $batch, ?Throwable $e) { /* ... */ })
                ->dispatch();
        }

        return $results;
    }
}
