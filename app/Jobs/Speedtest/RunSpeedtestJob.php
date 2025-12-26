<?php

namespace App\Jobs\Speedtest;

use App\Enums\ResultService;
use App\Enums\ResultStatus;
use App\Models\Result;
use App\Services\Speedtest\Drivers\CloudflareDriver;
use App\Services\Speedtest\Drivers\FastDriver;
use App\Services\Speedtest\Drivers\OoklaDriver;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunSpeedtestJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public Result $result
    ) {}

    public function handle(): void
    {
        $this->result->update(['status' => ResultStatus::Running]);
        
        // Dispatch SpeedtestRunning event if needed.
        // App\Events\SpeedtestRunning::dispatch($this->result);

        try {
            $driver = match ($this->result->service) {
                ResultService::Ookla => new OoklaDriver(),
                ResultService::Fast => new FastDriver(),
                ResultService::Cloudflare => new CloudflareDriver(),
                default => throw new \Exception("Unsupported service: {$this->result->service->value}"),
            };

            $driver->run($this->result);

        } catch (\Throwable $e) {
            Log::error($e);
            $this->result->update([
                'status' => ResultStatus::Failed,
                'data->error' => $e->getMessage(),
            ]);
            
            // App\Events\SpeedtestFailed::dispatch($this->result);

            if ($this->batch()) {
                $this->batch()->cancel();
            }
        }
    }
}
