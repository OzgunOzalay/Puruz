<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AdminUserSeeder::class, PageSeeder::class]);

        // Demo catalogue only outside production: php artisan db:seed --class=DemoSeeder to force it.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
