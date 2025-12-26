<?php

namespace App\Filament\Widgets;

use App\Enums\ResultStatus;
use App\Filament\Widgets\Concerns\HasChartFilters;
use App\Models\Result;
use Filament\Widgets\ChartWidget;

class RecentJitterChartWidget extends ChartWidget
{
    use HasChartFilters;

    protected ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('general.jitter');
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
            ->select(['id', 'service', 'data', 'created_at'])
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

        $services = $results->groupBy('service');

        foreach ($services as $serviceName => $serviceResults) {
            $isOokla = $serviceName === 'ookla';
            $borderDash = match ($serviceName) {
                'ookla' => [], // Solid
                'fast' => [5, 5], // Dashed
                'cloudflare' => [2, 2], // Dotted
                default => [10, 5], // Long Dotted
            };
            $labelSuffix = ' (' . ucfirst($serviceName) . ')';

            // Download Jitter (Blue)
            $datasets[] = [
                'label' => __('general.download_ms') . $labelSuffix,
                'data' => $results->map(fn ($item) => ($item->service instanceof \App\Enums\ResultService ? $item->service->value : $item->service) === $serviceName ? $item->download_jitter : null),
                'borderColor' => 'rgba(14, 165, 233)',
                'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                'pointBackgroundColor' => 'rgba(14, 165, 233)',
                'fill' => true,
                'tension' => 0.4,
                'pointRadius' => 0, // Reduce clutter
                'borderDash' => $borderDash,
                'spanGaps' => true,
            ];

            // Upload Jitter (Violet)
            $datasets[] = [
                'label' => __('general.upload_ms') . $labelSuffix,
                'data' => $results->map(fn ($item) => ($item->service instanceof \App\Enums\ResultService ? $item->service->value : $item->service) === $serviceName ? $item->upload_jitter : null),
                'borderColor' => 'rgba(139, 92, 246)',
                'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                'pointBackgroundColor' => 'rgba(139, 92, 246)',
                'fill' => true,
                'tension' => 0.4,
                'pointRadius' => 0,
                'borderDash' => $borderDash,
                'spanGaps' => true,
            ];

            // Ping Jitter (Green)
            $datasets[] = [
                'label' => __('general.ping_ms_label') . $labelSuffix,
                'data' => $results->map(fn ($item) => ($item->service instanceof \App\Enums\ResultService ? $item->service->value : $item->service) === $serviceName ? $item->ping_jitter : null),
                'borderColor' => 'rgba(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'pointBackgroundColor' => 'rgba(16, 185, 129)',
                'fill' => true,
                'tension' => 0.4,
                'pointRadius' => 0,
                'borderDash' => $borderDash,
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
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
