<?php

namespace App\Services\Speedtest\Drivers;

use App\Enums\ResultStatus;
use App\Events\SpeedtestFailed;
use App\Helpers\Ookla;
use App\Jobs\Ookla\RunSpeedtestJob; // Or should I extract logic?
// Actually I should extract the logic from RunSpeedtestJob to here, 
// OR have this driver use the existing logic. 
// For now, let's copy the logic from RunSpeedtestJob.

use App\Models\Result;
use App\Services\Speedtest\SpeedtestDriver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OoklaDriver extends SpeedtestDriver
{
    public function run(Result $result): void
    {
        $command = array_filter([
            'speedtest',
            '--accept-license',
            '--accept-gdpr',
            '--selection-details',
            '--format=json',
            app(\App\Settings\GeneralSettings::class)->speedtest_servers ? '--server-id='.app(\App\Settings\GeneralSettings::class)->speedtest_servers : null,
            config('speedtest.interface') ? '--interface='.config('speedtest.interface') : null,
        ]);

        $process = new Process($command);
        $process->setTimeout(120);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $result->update([
                'data->type' => 'log',
                'data->level' => 'error',
                'data->message' => Ookla::getErrorMessage($exception),
                'status' => ResultStatus::Failed,
            ]);

            SpeedtestFailed::dispatch($result);

            throw $exception; 
        }

        $output = json_decode($process->getOutput(), true);

        $result->update([
            'ping' => Arr::get($output, 'ping.latency'),
            'download' => Arr::get($output, 'download.bandwidth'),
            'upload' => Arr::get($output, 'upload.bandwidth'),
            'download_bytes' => Arr::get($output, 'download.bytes'),
            'upload_bytes' => Arr::get($output, 'upload.bytes'),
            'data' => $output,
        ]);
    }
}
