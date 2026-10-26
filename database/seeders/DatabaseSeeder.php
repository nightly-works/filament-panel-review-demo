<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        User::factory()->support()->create(['name' => 'Support', 'email' => 'support@example.com']);
        Invoice::factory()->count(10)->create();
        Invoice::factory()->count(3)->create(['status' => 'paid']);
    }
}
