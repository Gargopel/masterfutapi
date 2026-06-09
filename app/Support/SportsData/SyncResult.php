<?php

namespace App\Support\SportsData;

class SyncResult
{
    public function __construct(public bool $success, public string $message, public array $result = []) {}
}
