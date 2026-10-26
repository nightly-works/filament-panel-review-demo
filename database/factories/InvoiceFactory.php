<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'INV-'.fake()->unique()->numerify('#####'),
            'customer_name' => fake()->company(),
            'amount_cents' => fake()->numberBetween(5_000, 500_000),
            'status' => 'sent',
        ];
    }
}
