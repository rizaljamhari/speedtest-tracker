<?php

namespace App\Services\Speedtest\Drivers;

use App\Enums\ResultService;
use App\Models\Result;
use App\Services\Speedtest\SpeedtestDriver;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class CloudflareDriver extends SpeedtestDriver
{
    public function available(): bool
    {
        return (new ExecutableFinder)->find('cfspeedtest') !== null;
    }

    public function run(Result $result): void
    {
        $process = new Process(['cfspeedtest', '-n5', '-m100m', '--output-format', 'json']);
        $process->setTimeout(300);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
             throw new \RuntimeException('Cloudflare speedtest failed: ' . $exception->getMessage());
        }

        $output = json_decode($process->getOutput(), true);

        // Filter 10MB payload size
        $downloadMeasurements = collect($output['speed_measurements'])
            ->where('test_type', 'Download')
            ->where('payload_size', 10000000)
            ->first();

        $uploadMeasurements = collect($output['speed_measurements'])
            ->where('test_type', 'Upload')
            ->where('payload_size', 10000000)
            ->first();

        // Convert Mbps to Bytes: Mbps * 125000 = Bytes/s
        $downloadSpeed = $downloadMeasurements['avg'] ?? 0;
        $uploadSpeed = $uploadMeasurements['avg'] ?? 0;

        $result->update([
            'ping' => $output['latency_measurement']['avg_latency_ms'] ?? null,
            'download' => $downloadSpeed * 125000,
            'upload' => $uploadSpeed * 125000,
            'data' => [
                'server' => [
                    'id' => $output['metadata']['asn'] ?? null,
                    'name' => $output['metadata']['colo'] ?? null,
                    'location' => $output['metadata']['colo'] ?? null,
                ],
                'latency' => $output['latency_measurement'] ?? null,
                'speed' => $output['speed_measurements'] ?? null,
            ],
        ]);
    }
}
