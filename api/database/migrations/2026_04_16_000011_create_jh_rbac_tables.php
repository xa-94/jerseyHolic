<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 角色表
        Schema::create('jh_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('角色名称');
            $table->string('slug', 64)->comment('角色标识，如 super_admin, operator, customer_service');
            $table->string('guard', 32)->default('admin')->comment('守卫类型: admin/merchant');
            $table->string('description', 255)->default('')->comment('角色描述');
            $table->tinyInteger('is_system')->default(0)->comment('是否系统内置: 0=否, 1=是(不可删除)');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique(['slug', 'guard'], 'udx_roles_slug_guard');
        });

        // 权限表
        Schema::create('jh_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('权限名称');
            $table->string('slug', 128)->comment('权限标识，如 product.view, order.create');
            $table->string('module', 64)->comment('所属模块: product/order/payment/shipping/user/system等');
            $table->string('action', 32)->comment('操作: view/create/update/delete/export等');
            $table->string('description', 255)->default('')->comment('权限描述');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique('slug', 'udx_permissions_slug');
            $table->index('module', 'idx_permissions_module');
        });

        // 角色-权限关联
        Schema::create('jh_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->unsignedBigInteger('permission_id')->comment('权限ID');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id'], 'udx_role_permissions_role_perm');
            $table->index('permission_id', 'idx_role_permissions_perm_id');
        });

        // 管理员-角色关联
        Schema::create('jh_admin_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->comment('管理员ID');
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->timestamps();

            $table->unique(['admin_id', 'role_id'], 'udx_admin_roles_admin_role');
            $table->index('role_id', 'idx_admin_roles_role_id');
        });

        // 后台菜单
        Schema::create('jh_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父菜单ID，0=顶级');
            $table->string('title', 64)->comment('菜单标题');
            $table->string('icon', 64)->default('')->comment('菜单图标');
            $table->string('path', 255)->default('')->comment('前端路由路径');
            $table->string('permission_slug', 128)->default('')->comment('关联权限标识');
            $table->tinyInteger('type')->default(1)->comment('类型: 1=目录, 2=菜单, 3=按钮');
            $table->tinyInteger('is_visible')->default(1)->comment('是否可见: 0=隐藏, 1=可见');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('parent_id', 'idx_menus_parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_menus');
        Schema::dropIfExists('jh_admin_roles');
        Schema::dropIfExists('jh_role_permissions');
        Schema::dropIfExists('jh_permissions');
        Schema::dropIfExists('jh_roles');
    }
};
