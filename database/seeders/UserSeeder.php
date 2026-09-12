<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Primary test user: username=user, password=1234
        User::firstOrCreate(
            ['email' => 'user@nexuserp.com'],
            [
                'username' => 'user',
                'name'     => 'Mr System Admin',
                'email'    => 'user@nexuserp.com',
                'password' => Hash::make('1234'),
                'role'     => 'Admin',
                'status'   => 'active',
            ]
        );

        // Sample users matching POS/ERP structure
        $sampleUsers = [
            [
                'username' => 'admin1',
                'name'     => 'Mr Sithum',
                'email'    => 'info.sithum@gmail.com',
                'password' => Hash::make('password'),
                'role'     => 'Admin',
                'status'   => 'active',
            ],
            [
                'username' => 'alex',
                'name'     => 'Alexander Wright',
                'email'    => 'alexander@nexuserp.com',
                'password' => Hash::make('password'),
                'role'     => 'Manager',
                'status'   => 'active',
            ],
            [
                'username' => 'sarah',
                'name'     => 'Sarah Connor',
                'email'    => 'sarah@nexuserp.com',
                'password' => Hash::make('password'),
                'role'     => 'Finance Staff',
                'status'   => 'offline',
            ],
            [
                'username' => 'david',
                'name'     => 'David Miller',
                'email'    => 'david@nexuserp.com',
                'password' => Hash::make('password'),
                'role'     => 'Cashier',
                'status'   => 'active',
            ],
            [
                'username' => 'priya',
                'name'     => 'Priya Sharma',
                'email'    => 'priya@nexuserp.com',
                'password' => Hash::make('password'),
                'role'     => 'Manager',
                'status'   => 'active',
            ],
        ];

        foreach ($sampleUsers as $userData) {
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }
    }
}
