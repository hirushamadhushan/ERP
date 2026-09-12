<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'description' => 'Full access to all system modules and settings'],
            ['name' => 'Cashier', 'description' => 'Access to Point of Sale and billing functions'],
            ['name' => 'Manager', 'description' => 'Management access for inventory and reports'],
            ['name' => 'Finance Staff', 'description' => 'Financial records and accounts management'],
            ['name' => 'Inventory Specialist', 'description' => 'Stock management and transfer control'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
