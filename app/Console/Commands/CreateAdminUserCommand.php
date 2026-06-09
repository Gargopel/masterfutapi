<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'futia:admin:create {--email=} {--password=} {--name=FutIA Admin}';
    protected $description = 'Create or update an admin user for the FutIA admin panel.';

    public function handle(): int
    {
        $email = trim((string) ($this->option('email') ?: env('ADMIN_EMAIL')));
        $password = (string) ($this->option('password') ?: env('ADMIN_PASSWORD'));
        $name = trim((string) $this->option('name')) ?: 'FutIA Admin';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Set a valid ADMIN_EMAIL env value or pass --email.');
            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Set ADMIN_PASSWORD with at least 8 characters or pass --password.');
            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
        ])->save();

        $this->info("Admin user ready: {$user->email}");

        return self::SUCCESS;
    }
}
