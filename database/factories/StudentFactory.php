<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'student_id' => $this->faker->unique()->numerify('##-#####'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'contact_number' => $this->faker->numerify('09#########'),
            'sex' => $this->faker->randomElement(['Male', 'Female']),
            'birthdate' => $this->faker->date(),
            'year_level' => $this->faker->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
            'college' => 'College of Informatics and Computing Sciences',
            'program' => 'BS Information Technology',
            'section' => 'A',
            'is_active' => true,
        ];
    }
}