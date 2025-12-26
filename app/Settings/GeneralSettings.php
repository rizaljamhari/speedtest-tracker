<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $speedtest_schedule;

    public ?string $speedtest_server;

    public bool $ookla_enabled;

    public bool $fast_enabled;

    public bool $cloudflare_enabled;

    // parallel, sequential
    public string $execution_mode;

    public string $display_timezone;

    public string $chart_datetime_format;

    public string $datetime_format;

    public int $prune_results_older_than;

    public ?string $speedtest_servers;
    
    public ?string $speedtest_base_url;

    public static function group(): string
    {
        return 'general';
    }
}
