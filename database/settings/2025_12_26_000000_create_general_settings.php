<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.speedtest_schedule', '0 * * * *');
        $this->migrator->add('general.speedtest_server', null);
        $this->migrator->add('general.ookla_enabled', true);
        $this->migrator->add('general.fast_enabled', false);
        $this->migrator->add('general.execution_mode', 'sequential');
    }
};
