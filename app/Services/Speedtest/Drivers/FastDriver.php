<?php

namespace App\Services\Speedtest\Drivers;

use App\Enums\ResultStatus;
use App\Events\SpeedtestFailed;
use App\Models\Result;
use App\Services\Speedtest\SpeedtestDriver;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class FastDriver extends SpeedtestDriver
{
    public function run(Result $result): void
    {
        // fast-cli --json --upload --verbose
        $process = new Process(['fast', '--json', '--upload', '--verbose']);
        $process->setTimeout(120);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $result->update([
                'data->type' => 'log',
                'data->level' => 'error',
                'data->message' => $exception->getMessage(),
                'status' => ResultStatus::Failed,
            ]);

            SpeedtestFailed::dispatch($result);

            throw $exception;
        }

        $output = json_decode($process->getOutput(), true);

        $downloadMbps = Arr::get($output, 'downloadSpeed', 0);
        $uploadMbps = Arr::get($output, 'uploadSpeed', 0);
        $ping = Arr::get($output, 'latency', 0);

        $downloadBytesPerSec = $downloadMbps * 125000;
        $uploadBytesPerSec = $uploadMbps * 125000;

        // Map Fast.com data to Ookla-like structure for consistency
        $data = $output;
        
        // Map Client IP
        if ($ip = Arr::get($output, 'userIp')) {
            Arr::set($data, 'interface.externalIp', $ip);
        }
        
        // Map Location to Server Name (since Fast.com doesn't give a named server)
        // Map Location to Server Name from custom fast-cli output
        if ($serverLocations = Arr::get($output, 'serverLocations')) {
             $locations = implode(' | ', $serverLocations);
             Arr::set($data, 'server.name', $locations);
             Arr::set($data, 'server.location', $locations);
        } else {
            Arr::set($data, 'server.name', 'Fast.com');
        }
        
        // Ensure server ID is present (mock it or use a constant for Fast)
        Arr::set($data, 'server.id', 0); 

        // Map bytes transferred (downloaded/uploaded are in MB in the custom JSON)
        // Convert MB to Bytes: 1 MB = 1,000,000 Bytes (approx/decimal standard for network)
        // Or 1,048,576 for binary. Let's start with decimal to match Mbps logic (125000).
        $downloadBytes = Arr::get($output, 'downloaded', 0) * 1000000;
        $uploadBytes = Arr::get($output, 'uploaded', 0) * 1000000;

        $result->update([
            'ping' => $ping,
            'download' => $downloadBytesPerSec,
            'upload' => $uploadBytesPerSec,
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'data' => $data,
        ]);
    }
}
