<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class PackageMasterSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // === Guides Table (Assume 'users' table with role 'guide') ===
        $guideIds = [];
        for ($i = 1; $i <= 50; $i++) {
            // Insert into users table
            $userId = DB::table('users')->insertGetId([
                'name' => $faker->name,
                'email' => "guide$i@example.com",
                'password' => bcrypt('sahaanik'),
                'role' => 'guide',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert into guides table
            DB::table('guides')->insert([
                'user_id' => $userId,
                'bio' => $faker->sentence(10),
                'phone' => $faker->phoneNumber,
                'rating' => rand(3, 5),
            ]);

            $guideIds[] = $userId;
        }

        // === Packages Table ===
        $packageIds = [];
        $tripIds = DB::table('trips')->pluck('id')->toArray();

        for ($i = 1; $i <= 2; $i++) {
            $id = DB::table('packages')->insertGetId([
                'name' => 'Package ' . $i,
                'description' => $faker->sentence(10),
                'includes_meal' => rand(0, 1),
                'includes_hotel' => rand(0, 1),
                'includes_bus' => rand(0, 1),
                'trip_id' => $faker->randomElement($tripIds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $packageIds[] = $id;
        }

        // === Price Packages ===
        foreach ($packageIds as $packageId) {
            DB::table('price_packages')->insert([
                'package_id' => $packageId,
                'adult_price' => rand(25000, 25000),
                'child_price' => rand(15000, 20000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === Package Inclusions ===
        $inclusionItems = ['Hotel Stay', 'Welcome Drink', 'City Tour', 'Lunch Buffet', 'Pickup & Drop'];
        for ($i = 1; $i <= 2; $i++) {
            DB::table('package_inclusions')->insert([
                'package_id' => $faker->randomElement($packageIds),
                'item_name' => $faker->randomElement($inclusionItems),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === Package Exclusions ===
        $exclusionItems = ['Laundry', 'Drinks', 'Personal Expense', 'Tips', 'Additional Meals'];
        for ($i = 1; $i <= 2; $i++) {
            DB::table('package_exclusions')->insert([
                'package_id' => $faker->randomElement($packageIds),
                'item_name' => $faker->randomElement($exclusionItems),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === Guide-Package Assignments ===
        for ($i = 1; $i <= 2; $i++) {
            DB::table('guide_packages')->insert([
                'guide_id' => $faker->randomElement($guideIds),
                'package_id' => $faker->randomElement($packageIds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // === Guide Performance ===
        for ($i = 1; $i <= 2; $i++) {
            DB::table('guide_performances')->insert([
                'guide_id' => $faker->randomElement($guideIds),
                'package_id' => $faker->randomElement($packageIds),
                'rating' => rand(3, 5),
                'feedback' => $faker->sentence(8),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Package related tables seeded with 50 records each!');
    }
}
