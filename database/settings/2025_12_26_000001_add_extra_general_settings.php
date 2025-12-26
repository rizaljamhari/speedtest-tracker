<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.display_timezone', 'Asia/Kuala_Lumpur');
        $this->migrator->add('general.chart_datetime_format', 'j/m g:i A');
        $this->migrator->add('general.datetime_format', 'j M Y, g:i A');
        $this->migrator->add('general.prune_results_older_than', 35);
        $this->migrator->add('general.speedtest_servers', '29925,29925,60357');
        $this->migrator->add('general.speedtest_base_url', 'https://google.com');
    }
};
