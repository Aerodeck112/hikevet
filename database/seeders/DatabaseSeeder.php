<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Date de baza, necesare si in productie: roluri, lista de preturi, continutul public al site-ului. */
    public function run(): void
    {
        $this->call([RolesSeeder::class, PriceListSeeder::class, SiteContentSeeder::class]);
    }
}
