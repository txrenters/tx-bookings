<?php

namespace Database\Seeders;

use App\Actions\Scheduling\ApplyDefaultHolidays;
use App\Actions\Scheduling\CreateDefaultAvailability;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The default accounts for local use; everyone logs in with "password".
     *
     * Registration is invitation-only, so the first accounts have to come
     * from here. Further teammates are invited from inside the app.
     *
     * @var array<int, array{name: string, email: string}>
     */
    protected array $defaultUsers = [
        ['name' => 'TexasRenters Admin', 'email' => 'automation@texasrenters.com'],
        ['name' => 'Test User', 'email' => 'test@example.com'],
    ];

    /**
     * Seed the application's database.
     *
     * Safe to rerun: accounts that already exist are left untouched.
     */
    public function run(): void
    {
        foreach ($this->defaultUsers as $defaults) {
            if (User::query()->where('email', $defaults['email'])->exists()) {
                continue;
            }

            $user = User::factory()->create($defaults);

            // Mirror registration, so the account is bookable right away.
            app(CreateDefaultAvailability::class)->handle($user);
            app(ApplyDefaultHolidays::class)->handle($user);
        }
    }
}
