<?php

namespace App\Filament\Pages\Settings;

use App\Settings\GeneralSettings;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class General extends SettingsPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static string $settings = GeneralSettings::class;

    public function getTitle(): string
    {
        return 'General Settings';
    }

    public static function getNavigationLabel(): string
    {
        return 'General';
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->schema([
                        Tab::make('General')
                            ->schema([
                                \Filament\Forms\Components\Select::make('display_timezone')
                                    ->label('Display Timezone')
                                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('datetime_format')
                                    ->label('Date & Time Format')
                                    ->helperText('PHP date format (e.g., j M Y, g:i A)'),
                                TextInput::make('chart_datetime_format')
                                    ->label('Chart Date Format')
                                    ->helperText('e.g., j/m g:i A'),
                            ]),

                        Tab::make('Speedtest')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                ])
                                    ->schema([
                                        Section::make('Configuration')
                                            ->schema([
                                                TextInput::make('speedtest_schedule')
                                                    ->label('Schedule (Cron Expression)')
                                                    ->helperText('Leave empty to disable scheduled tests.')
                                                    ->placeholder('0 * * * *'),
                                                TextInput::make('speedtest_servers')
                                                    ->label('Server IDs')
                                                    ->helperText('Comma-separated list of server IDs (e.g., 1234,5678).'),
                                                TextInput::make('speedtest_base_url')
                                                    ->label('Connectivity Check URL')
                                                    ->helperText('URL to check internet connectivity before running tests.'),
                                            ])->columnSpan(1),

                                        Section::make('Services')
                                            ->schema([
                                                Toggle::make('ookla_enabled')
                                                    ->label('Enable Ookla Speedtest')
                                                    ->helperText('Uses the official Ookla CLI.'),
                                                Toggle::make('fast_enabled')
                                                    ->label('Enable Fast.com')
                                                    ->helperText('Uses fast-cli (Netflix).'),
                                                Toggle::make('cloudflare_enabled')
                                                    ->label('Enable Cloudflare Speedtest')
                                                    ->helperText('Uses cfspeedtest (Cloudflare).'),
                                                
                                                Radio::make('execution_mode')
                                                    ->label('Execution Mode')
                                                    ->options([
                                                        'sequential' => 'Sequential (One after another)',
                                                        'parallel' => 'Parallel (Run at the same time)',
                                                    ])
                                                    ->default('sequential')
                                                    ->required(),
                                            ])->columnSpan(1),
                                    ]),
                            ]),

                        Tab::make('System')
                            ->schema([
                                TextInput::make('prune_results_older_than')
                                    ->label('Prune Results (Days)')
                                    ->numeric()
                                    ->helperText('Set to 0 to disable pruning.')
                                    ->required(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
