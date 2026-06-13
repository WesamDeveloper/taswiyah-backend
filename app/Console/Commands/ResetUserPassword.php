<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-password {email} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a user password by their email address.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $newPassword = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Account with email [{$email}] was not found.");
            return Command::FAILURE;
        }

        // If no password provided, generate a random 8-character string
        if (!$newPassword) {
            $newPassword = Str::random(8);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        $this->info("Password for [{$user->name}] has been successfully reset!");
        $this->warn("New Password: " . $newPassword);

        return Command::SUCCESS;
    }
}
