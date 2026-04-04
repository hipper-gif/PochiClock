<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('本番環境でのシード実行は禁止されています。');
            return;
        }

        $dept1 = Department::create(['name' => '介護部門']);
        $dept2 = Department::create(['name' => '配食部門']);
        $dept3 = Department::create(['name' => '事務・管理']);

        User::create([
            'employee_number' => '0001',
            'name' => '管理者',
            'email' => 'admin@example.com',
            'password' => 'dev-password-0001',
            'role' => Role::ADMIN,
            'department_id' => $dept3->id,
            'kiosk_code' => '0001',
        ]);

        User::create([
            'employee_number' => '0002',
            'name' => 'テスト社員',
            'email' => 'employee@example.com',
            'password' => 'dev-password-0002',
            'role' => Role::EMPLOYEE,
            'department_id' => $dept1->id,
            'kiosk_code' => '0002',
        ]);
    }
}
