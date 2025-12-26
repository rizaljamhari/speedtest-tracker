<?php

namespace App\Filament\Widgets\Concerns;

trait HasChartFilters
{
    protected function getFilters(): ?array
    {
        $times = [
            '24h' => 'Last 24 Hours',
            'week' => 'Last 7 Days',
            'month' => 'Last 30 Days',
        ];

        $services = [
            'all' => 'All Services',
            'ookla' => 'Ookla',
            'fast' => 'Fast.com',
            'cloudflare' => 'Cloudflare',
        ];

        $filters = [];

        foreach ($times as $timeKey => $timeLabel) {
            foreach ($services as $serviceKey => $serviceLabel) {
                // Use a pipe separator: time|service
                $filters["{$timeKey}|{$serviceKey}"] = "{$timeLabel} ({$serviceLabel})";
            }
        }

        return $filters;
    }
}
