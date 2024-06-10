<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        User::updateOrCreate([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password')
        ]);       User::updateOrCreate([
            'name' => 'Admin 1',
            'email' => 'admin2@gmail.com',
            'password' => bcrypt('password')
        ]);   User::updateOrCreate([
            'name' => 'Admin 1',
            'email' => 'admin3@gmail.com',
            'password' => bcrypt('password')
        ]);   User::updateOrCreate([
            'name' => 'Admin 1',
            'email' => 'admin1@gmail.com',
            'password' => bcrypt('password')
        ]);
        // \App\Models\User::factory(10)->create();
    }
}
