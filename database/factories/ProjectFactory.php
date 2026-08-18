<?php

namespace Database\Factories;

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name'    => $name,
            'slug'    => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'token'   => 'proj_'.Str::random(40),
        ];
    }
}
