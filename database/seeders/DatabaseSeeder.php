<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GameRoleSeeder::class);

        if (app()->environment('local')) {
            $this->call(DevSeeder::class);
        }
    }
}
