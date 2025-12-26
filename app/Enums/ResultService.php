<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ResultService: string implements HasLabel
{
    case Cloudflare = 'cloudflare';
    case Faker = 'faker';
    case Fast = 'fast';
    case Librespeed = 'librespeed';
    case Ookla = 'ookla';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cloudflare => 'Cloudflare',
            self::Faker => __('enums.service.faker'),
            self::Fast => 'Fast.com',
            self::Librespeed => __('enums.service.librespeed'),
            self::Ookla => __('enums.service.ookla'),
        };
    }
}
