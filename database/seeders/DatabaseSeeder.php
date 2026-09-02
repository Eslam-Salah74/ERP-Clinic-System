<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Setup\Database\Seeders\SetupDatabaseSeeder;
use Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder;
use Modules\Reception\Database\Seeders\ReceptionDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * ترتيب التنفيذ مهم جداً:
     * 1. Auth أول (بيعمل Roles + Permissions + Super Admin)
     * 2. باقي الموديولات (كل منها بيضيف Permissions ويتم تحديث Super Admin تلقائياً)
     * 3. لو أضفت موديول جديد، ضيفه هنا وكل شيء هيشتغل تلقائياً
     */
    public function run(): void
    {
        // 1. Auth: إنشاء الأدوار والصلاحيات الأساسية + حساب السوبر أدمن + الموظفين
        $this->call(AuthDatabaseSeeder::class);

        // 2. Setup: صلاحيات وبيانات الإعدادات (أقسام، خدمات، إعدادات)
        $this->call(SetupDatabaseSeeder::class);

        // 3. Inventory: صلاحيات وبيانات المخزون (موردين، أصناف، فواتير)
        $this->call(InventoryDatabaseSeeder::class);

        // 4. Reception: صلاحيات وبيانات الاستقبال (مرضى، مواعيد، شيفتات، فواتير)
        $this->call(ReceptionDatabaseSeeder::class);

        // ➕ لو أضفت موديول جديد (مثلاً HR)، ضيفه هنا:
        // $this->call(\Modules\HR\Database\Seeders\HRDatabaseSeeder::class);
    }
}

