<?php

namespace Tests;

use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function apiHeaders(?User $user = null): array
    {
        $user ??= User::factory()->create(['is_admin' => false]);
        [, $plainTextToken] = UserApiToken::issueFor($user, 'Test key');

        return ['Authorization' => 'Bearer '.$plainTextToken];
    }
}
