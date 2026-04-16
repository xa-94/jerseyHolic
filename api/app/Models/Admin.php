<?php

namespace App\Models;

use App\Models\Central\Admin as CentralAdmin;

/**
 * 管理员模型 — 兼容层
 *
 * @deprecated 请使用 App\Models\Central\Admin 代替。
 *             本文件保留用于旧代码的向后兼容，新代码应直接引用 Central\Admin。
 *
 * @see \App\Models\Central\Admin
 */
class Admin extends CentralAdmin
{
    // 继承自 Central\Admin，不做任何修改。
    // 旧代码中的 App\Models\Admin 引用自动继承 Central DB 连接和全部功能。
}
