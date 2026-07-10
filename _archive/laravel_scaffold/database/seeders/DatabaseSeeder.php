<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdminUserSeeder::class,
            LeaguesSeeder::class,
            SeasonsSeeder::class,
            TeamsSeeder::class,
        ]);
    }
}