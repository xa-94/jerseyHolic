<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // 插入默认超级管理员（已存在则跳过）
        $exists = DB::table('jh_admins')->where('username', 'admin')->exists();

        if (! $exists) {
            $adminId = DB::table('jh_admins')->insertGetId([
                'username'   => 'admin',
                'email'      => 'admin@jerseyholic.com',
                'password'   => Hash::make('admin123'),
                'name'       => 'Super Admin',
                'status'     => 1,
                'is_super'   => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Admin account created (id={$adminId}).");
        } else {
            $adminId = DB::table('jh_admins')->where('username', 'admin')->value('id');
            $this->command->info("Admin account already exists (id={$adminId}), skipped.");
        }

        // 插入默认 super_admin 角色（已存在则跳过）
        $roleExists = DB::table('jh_roles')->where('slug', 'super_admin')->where('guard', 'admin')->exists();

        if (! $roleExists) {
            $roleId = DB::table('jh_roles')->insertGetId([
                'name'        => 'Super Admin',
                'slug'        => 'super_admin',
                'guard'       => 'admin',
                'description' => '系统超级管理员，拥有全部权限',
                'is_system'   => 1,
                'sort_order'  => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $this->command->info("Role [super_admin] created (id={$roleId}).");
        } else {
            $roleId = DB::table('jh_roles')->where('slug', 'super_admin')->where('guard', 'admin')->value('id');
            $this->command->info("Role [super_admin] already exists (id={$roleId}), skipped.");
        }

        // 关联管理员与角色（已关联则跳过）
        $linked = DB::table('jh_admin_roles')
            ->where('admin_id', $adminId)
            ->where('role_id', $roleId)
            ->exists();

        if (! $linked) {
            DB::table('jh_admin_roles')->insert([
                'admin_id'   => $adminId,
                'role_id'    => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Admin (id={$adminId}) linked to role [super_admin] (id={$roleId}).");
        } else {
            $this->command->info("Admin already linked to role [super_admin], skipped.");
        }
    }
}
