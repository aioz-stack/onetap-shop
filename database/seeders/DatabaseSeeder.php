<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Every seeder is idempotent and safe to re-run.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
            CategorySeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call([DemoAccountsSeeder::class, DemoCatalogSeeder::class]);
        }
    }
}
