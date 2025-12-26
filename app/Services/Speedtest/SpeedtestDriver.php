<?php

namespace App\Services\Speedtest;

use App\Models\Result;

abstract class SpeedtestDriver
{
    /**
     * Run the speedtest and update the result model.
     *
     * @param  Result  $result
     * @return void
     */
    abstract public function run(Result $result): void;

    /**
     * Parse the output error and return a user-friendly message.
     *
     * @param  \Throwable  $exception
     * @return string
     */
    public function getErrorMessage(\Throwable $exception): string
    {
        return $exception->getMessage();
    }
}
