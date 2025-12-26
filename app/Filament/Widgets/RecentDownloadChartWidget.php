<?php

namespace App\Filament\Widgets;

use App\Enums\ResultStatus;
use App\Filament\Widgets\Concerns\HasChartFilters;
use App\Helpers\Average;
use App\Helpers\Number;
use App\Models\Result;
use Filament\Widgets\ChartWidget;

class RecentDownloadChartWidget extends ChartWidget
{
    use HasChartFilters;

    protected ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('general.download_mbps');
    }

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    protected ?string $pollingInterval = '60s';

    public ?string $filter = null;

    public function mount(): void
    {
        $this->filter = $this->filter ?? (config('speedtest.default_chart_range', '24h') . '|all');
    }

    protected function getData(): array
    {
        // Parse filter: "time|service" (e.g. "24h|all", "week|ookla")
        $filterParts = explode('|', $this->filter ?? '24h|all');
        $timeFilter = $filterParts[0] ?? '24h';
        $serviceFilter = $filterParts[1] ?? 'all';

        $results = Result::query()
            ->select(['id', 'service', 'download', 'created_at'])
            ->where('status', '=', ResultStatus::Completed)
            ->when($serviceFilter !== 'all', function ($query) use ($serviceFilter) {
                // Determine the enum value if possible, or just compare string if that matches DB
                // DB stores 'ookla', 'fast'.
                // If using SQLite/MySQL string comparison is fine.
                // But strict mode might require enum mapping if strict comparison was the issue before.
                // However, query builder `where` handles string vs enum casting usually if model casts it.
                // But let's be safe and pass the string since DB column is string/enum.
                $query->where('service', $serviceFilter);
            })
            ->when($timeFilter === '24h', function ($query) {
                $query->where('created_at', '>=', now()->subDay());
            })
            ->when($timeFilter === 'week', function ($query) {
                $query->where('created_at', '>=', now()->subWeek());
            })
            ->when($timeFilter === 'month', function ($query) {
                $query->where('created_at', '>=', now()->subMonth());
            })
            ->orderBy('created_at')
            ->get();

        $datasets = [];

        // Group results by service
        $services = $results->groupBy('service');

        foreach ($services as $serviceName => $serviceResults) {
            $color = match ($serviceName) {
                'ookla' => '14, 165, 233', // Sky Blue
                'fast' => '220, 38, 38',   // Red
                'cloudflare' => '249, 115, 22', // Orange
                default => '139, 92, 246', // Violet
            };

            // Map data to the global timeline (labels)
            // Since we are using an ordinal scale (labels array), we need to ensure alignment.
            // However, simply pushing data points often works if x-axis is not strict time-scale.
            // But to be correct with "labels" being all timestamps, we should map values to those timestamps.
            // Simpler approach for now: Just map the values and let Chart.js handle the "sparse" nature if we use explicit x/y structures,
            // OR, if we just want to show lines, we can just dump the values if they are sequential.
            //
            // WAIT: If we have Labels [T1, T2, T3].
            // Ookla has T1. Fast has T2. Ookla has T3.
            // Ookla Data: [V1, null, V3]
            // Fast Data: [null, V2, null]
            // This is required for correct alignment.

            $data = $results->map(function ($result) use ($serviceName) {
                $serviceValue = $result->service instanceof \App\Enums\ResultService ? $result->service->value : $result->service;
                if ($serviceValue === $serviceName) {
                    return ! blank($result->download) ? Number::bitsToMagnitude(bits: $result->download_bits, precision: 2, magnitude: 'mbit') : null;
                }
                return null;
            });

            $datasets[] = [
                'label' => ucfirst($serviceName),
                'data' => $data,
                'borderColor' => "rgba($color, 1)",
                'backgroundColor' => "rgba($color, 0.1)",
                'pointBackgroundColor' => "rgba($color, 1)",
                'fill' => true,
                'cubicInterpolationMode' => 'monotone',
                'tension' => 0.4,
                'pointRadius' => count($results) <= 24 ? 3 : 0,
                'spanGaps' => true, // Connect lines across nulls? Maybe false is better to show distinct tests. Let's try true for smooth graph.
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $results->map(fn ($item) => $item->created_at->timezone(config('app.display_timezone'))->format(config('app.chart_datetime_format'))),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,

                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'position' => 'nearest',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => config('app.chart_begin_at_zero'),
                    'grace' => 2,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
