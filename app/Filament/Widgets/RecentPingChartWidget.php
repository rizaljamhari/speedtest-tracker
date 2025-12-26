<?php

namespace App\Filament\Widgets;

use App\Enums\ResultStatus;
use App\Filament\Widgets\Concerns\HasChartFilters;
use App\Helpers\Average;
use App\Models\Result;
use Filament\Widgets\ChartWidget;

class RecentPingChartWidget extends ChartWidget
{
    use HasChartFilters;

    protected ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('general.ping_ms');
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
        $filterParts = explode('|', $this->filter ?? '24h|all');
        $timeFilter = $filterParts[0] ?? '24h';
        $serviceFilter = $filterParts[1] ?? 'all';

        $results = Result::query()
            ->select(['id', 'service', 'ping', 'created_at'])
            ->where('status', '=', ResultStatus::Completed)
            ->when($serviceFilter !== 'all', function ($query) use ($serviceFilter) {
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

            $data = $results->map(function ($result) use ($serviceName) {
                $serviceValue = $result->service instanceof \App\Enums\ResultService ? $result->service->value : $result->service;
                if ($serviceValue === $serviceName) {
                    return $result->ping;
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
                'spanGaps' => true,
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
