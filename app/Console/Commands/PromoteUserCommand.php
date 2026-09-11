<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserCommand extends Command
{
    protected $signature = 'user:promote {email : The email address of the user to promote} {role=admin : Role (admin, super_admin, manager)}';
    protected $description = 'Promote a staff account to Administrator role and approve status';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        $user->update([
            'role' => $role,
            'status' => 'approved',
        ]);

        $this->info("Successfully promoted {$user->name} ({$user->email}) to '{$role}' with 'approved' status.");
        return Command::SUCCESS;
    }
}
