<?php

namespace Database\Seeders;

use App\Ldap\LdapUserModel;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        /*LdapUserModel::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);*/

        $this->call([
            LdapRichSeeder::class,
            CreateGravataOuSeeder::class,
            GravataUsersSeeder::class,
        ]);
    }
}
