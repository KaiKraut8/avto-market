<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Makes one account the marketplace's only admin; every other account becomes a regular one.
// There is deliberately no way to become admin from the website.
class MakeAdmin extends Command
{
    protected $signature = 'users:admin {email : the account that becomes the only admin}
                            {--create : create the account if it doesn\'t exist (prints a temporary password)}
                            {--name=KAI Garage : name for a created account}';

    protected $description = 'Make one account the only admin';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();
        $password = null;

        if (! $user) {
            if (! $this->option('create')) {
                $this->error("No account with {$email}. Sign up with it first, or add --create.");

                return self::FAILURE;
            }
            $password = Str::password(16, symbols: false);
            $user = User::create([
                'name' => $this->option('name'),
                'email' => $email,
                'password' => $password,
                'phone' => config('company.phone'),
                'location' => 'Ljubljana',
                'country' => 'Slovenia',
            ]);
        }

        DB::transaction(function () use ($user) {
            User::whereKeyNot($user->id)->where('is_admin', true)->update(['is_admin' => false]);
            $user->forceFill(['is_admin' => true])->save();
        });

        $this->info("{$user->email} is now the only admin.");
        if ($password) {
            $this->warn("Account created. Temporary password: {$password}");
            $this->warn('Log in and change it under Profile → Change password.');
        }

        return self::SUCCESS;
    }
}
