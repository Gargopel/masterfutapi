<?php

namespace App\Support\SportsData;

class ProviderTestResult
{
    public function __construct(public bool $success, public ?string $message = null, public array $meta = []) {}
}
