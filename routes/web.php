<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

Route::get('/', function () {
    return view('welcome');
});

// ==========================================
// أوامر الصيانة والتشغيل على السيرفر
// ==========================================

/**
 * تنظيف وإعادة ضبط الكاش على السيرفر
 */
Route::get('/clear-cache', function () {
    set_time_limit(300);
    try {
        Artisan::call('cache:clear');
        $cacheOut = Artisan::output();

        Artisan::call('config:clear');
        $configOut = Artisan::output();

        Artisan::call('route:clear');
        $routeOut = Artisan::output();

        Artisan::call('view:clear');
        $viewOut = Artisan::output();

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
        $permOut = "Permissions cache reset successfully.\n";

        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #0f172a; color: #f8fafc; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2 style='color: #4ade80;'>✅ تم تنظيف كافة أنواع الكاش بنجاح!</h2>
            <hr style='border-color: #334155; margin: 20px 0;'>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #38bdf8; white-space: pre-wrap;'>{$cacheOut}{$configOut}{$routeOut}{$viewOut}{$permOut}</pre>
        </div>";
    } catch (\Throwable $e) {
        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #450a0a; color: #fecaca; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2>❌ خطأ أثناء تنظيف الكاش:</h2>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #f87171; white-space: pre-wrap;'>" . htmlspecialchars($e->getMessage()) . "</pre>
        </div>";
    }
});

/**
 * تشغيل الـ Migrations للمشروع الرئيسي وكافة الموديولات
 */
Route::get('/migrate', function () {
    set_time_limit(300);
    try {
        // 1. تفعيل جميع الموديولات تلقائياً لضمان قراءتها على السيرفر
        $allDiscoveredModules = Module::all();
        foreach ($allDiscoveredModules as $mod) {
            if (!$mod->isEnabled()) {
                $mod->enable();
            }
        }

        // 2. فحص وجود موديول HR على السيرفر
        $hrPath = base_path('Modules/HR');
        $hrFolderExists = is_dir($hrPath);
        $hrMigrationsPath = base_path('Modules/HR/database/migrations');
        $hrMigrationsExist = is_dir($hrMigrationsPath);
        $hrFilesCount = $hrMigrationsExist ? count(glob($hrMigrationsPath . '/*.php')) : 0;

        // 3. تشغيل الميجريشن العام للمشروع
        Artisan::call('migrate', ['--force' => true]);
        $mainOutput = Artisan::output();

        // 4. تشغيل الميجريشن المباشر عبر مسارات كل موديول للتأكد بنسبة 100%
        $modulePathOutputs = [];
        $migrationDirs = glob(base_path('Modules/*/database/migrations'));

        foreach ($migrationDirs as $dir) {
            // استخراج اسم الموديول من المسار
            preg_match('/Modules[\/\\\\]([^\/\\\\]+)[\/\\\\]database[\/\\\\]migrations/', $dir, $matches);
            $modName = $matches[1] ?? basename(dirname(dirname($dir)));

            $relPath = str_replace([base_path() . DIRECTORY_SEPARATOR, base_path() . '/'], '', $dir);

            Artisan::call('migrate', [
                '--path' => $relPath,
                '--force' => true,
            ]);

            $out = trim(Artisan::output());
            $modulePathOutputs[$modName] = $out ?: 'Nothing to migrate.';
        }

        // 5. مسح كاش الصلاحيات
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $html = "<div style='font-family: Arial, sans-serif; padding: 25px; background: #0f172a; color: #f8fafc; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2 style='color: #4ade80;'>✅ تقرير تنفيذ Migrations على السيرفر</h2>
            <hr style='border-color: #334155; margin: 20px 0;'>";

        // تنبيه في حالة عدم وجود مجلد HR على السيرفر
        if (!$hrFolderExists) {
            $html .= "<div style='background: #7f1d1d; border: 1px solid #ef4444; color: #fee2e2; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                <h3 style='margin-top: 0;'>⚠️ تحذير: مجلد موديول HR غير موجود على السيرفر!</h3>
                <p>المسار <code>Modules/HR</code> غير موجود. يرجى التأكد من رفع مجلد <b>Modules/HR</b> بكامل ملفاته إلى السيرفر عبر Git أو cPanel File Manager ثم إعادة فتح هذا الرابط.</p>
            </div>";
        } else {
            $html .= "<div style='background: #064e3b; border: 1px solid #10b981; color: #d1fae5; padding: 12px; border-radius: 8px; margin-bottom: 20px;'>
                <b>✔ حالة موديول HR:</b> تم العثور على المجلد وعدد ملفات الميجريشن: ({$hrFilesCount} ملفات).
            </div>";
        }

        $html .= "<h3 style='color: #93c5fd;'>1. Main Project Migrations:</h3>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #38bdf8; white-space: pre-wrap;'>" . htmlspecialchars($mainOutput ?: "Nothing to migrate.") . "</pre>";

        $html .= "<h3 style='color: #93c5fd; margin-top: 20px;'>2. Modules Migrations Detail:</h3>";
        foreach ($modulePathOutputs as $mod => $out) {
            $isHr = ($mod === 'HR');
            $headerColor = $isHr ? '#f59e0b' : '#a5b4fc';
            $html .= "<h4 style='color: {$headerColor}; margin-bottom: 5px;'>📦 موديول: {$mod}" . ($isHr ? ' (موديول الرواتب والعقود)' : '') . "</h4>
            <pre style='background: #1e293b; padding: 12px; border-radius: 6px; color: #38bdf8; white-space: pre-wrap; margin-bottom: 12px;'>" . htmlspecialchars($out) . "</pre>";
        }

        $html .= "</div>";
        return $html;
    } catch (\Throwable $e) {
        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #450a0a; color: #fecaca; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2>❌ خطأ أثناء تنفيذ Migrate:</h2>
            <p><b>السبب:</b> " . htmlspecialchars($e->getMessage()) . "</p>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #f87171; white-space: pre-wrap;'>في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "</pre>
        </div>";
    }
});

/**
 * ربط مجلد الملفات والمرفقات Storage
 */
Route::get('/storage-link', function () {
    try {
        Artisan::call('storage:link');
        $output = Artisan::output();
        return "<div style='font-family: Arial, sans-serif; padding: 20px; background: #0f172a; color: #4ade80; border-radius: 8px;'>
            <h3>✅ Storage Link:</h3>
            <pre style='background: #1e293b; padding: 15px; border-radius: 6px; color: #fff;'>" . htmlspecialchars($output ?: "Storage link checked.") . "</pre>
        </div>";
    } catch (\Throwable $e) {
        return "<div style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
});

/**
 * تشغيل السيدر الشامل لكافة الموديولات والصلاحيات (DatabaseSeeder) مع --force
 */
Route::get('/db-seed', function () {
    set_time_limit(300);
    try {
        // تفعيل كل الموديولات
        foreach (Module::all() as $mod) {
            if (!$mod->isEnabled()) {
                $mod->enable();
            }
        }

        Artisan::call('db:seed', ['--force' => true]);
        $output = Artisan::output();

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #0f172a; color: #f8fafc; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2 style='color: #4ade80;'>✅ تم تنفيذ الـ Seed بنجاح (DatabaseSeeder)!</h2>
            <hr style='border-color: #334155; margin: 20px 0;'>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #38bdf8; white-space: pre-wrap;'>" . htmlspecialchars($output ?: "Database seeded successfully.") . "</pre>
        </div>";
    } catch (\Throwable $e) {
        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #450a0a; color: #fecaca; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2>❌ خطأ أثناء تنفيذ db:seed:</h2>
            <p><b>السبب:</b> " . htmlspecialchars($e->getMessage()) . "</p>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #f87171; white-space: pre-wrap;'>في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "</pre>
        </div>";
    }
});

/**
 * تشغيل سيدرات الموديولات النشطة مع --force وبدون Prompts
 */
Route::get('/seed', function () {
    set_time_limit(300);
    try {
        // تفعيل كل الموديولات
        foreach (Module::all() as $mod) {
            if (!$mod->isEnabled()) {
                $mod->enable();
            }
        }

        $modules = Module::allEnabled();
        if (empty($modules)) {
            return "No enabled modules found.";
        }

        $outputs = [];
        $seededCount = 0;

        foreach ($modules as $module) {
            try {
                Artisan::call('module:seed', [
                    'module' => [$module->getName()],
                    '--force' => true,
                ]);
                $outputs[$module->getName()] = Artisan::output();
                $seededCount++;
            } catch (\Throwable $mEx) {
                $outputs[$module->getName()] = "Info/Skip: " . $mEx->getMessage();
            }
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $html = "<div style='font-family: Arial, sans-serif; padding: 25px; background: #0f172a; color: #f8fafc; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2 style='color: #4ade80;'>✅ تم تنفيذ السيدر لـ ({$seededCount}) موديول بنجاح!</h2>
            <hr style='border-color: #334155; margin: 20px 0;'>";

        foreach ($outputs as $mod => $out) {
            $html .= "<h4 style='color: #93c5fd; margin-bottom: 5px;'>📦 موديول: {$mod}</h4>
            <pre style='background: #1e293b; padding: 12px; border-radius: 6px; color: #38bdf8; white-space: pre-wrap; margin-bottom: 15px;'>" . htmlspecialchars($out ?: "Done.") . "</pre>";
        }

        $html .= "</div>";
        return $html;
    } catch (\Throwable $e) {
        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #450a0a; color: #fecaca; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2>❌ خطأ أثناء تنفيذ سيدر الموديولات:</h2>
            <p><b>السبب:</b> " . htmlspecialchars($e->getMessage()) . "</p>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #f87171; white-space: pre-wrap;'>في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "</pre>
        </div>";
    }
});

/**
 * مسار شامل ومريح للسيرفر: تنظيف كاش + تشغيل Migrations + تشغيل Seed دفعة واحدة
 */
Route::get('/deploy', function () {
    set_time_limit(300);
    try {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');

        // 1. تفعيل الموديولات
        foreach (Module::all() as $mod) {
            if (!$mod->isEnabled()) {
                $mod->enable();
            }
        }

        // 2. Migrations
        Artisan::call('migrate', ['--force' => true]);
        $migrateOut = Artisan::output();

        // 3. Modules Migrations
        $migrationDirs = glob(base_path('Modules/*/database/migrations'));
        foreach ($migrationDirs as $dir) {
            $relPath = str_replace([base_path() . DIRECTORY_SEPARATOR, base_path() . '/'], '', $dir);
            Artisan::call('migrate', [
                '--path' => $relPath,
                '--force' => true,
            ]);
        }

        // 4. Database Seed
        Artisan::call('db:seed', ['--force' => true]);
        $seedOut = Artisan::output();

        // 5. Permissions cache
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #0f172a; color: #f8fafc; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2 style='color: #4ade80;'>🚀 تم إعداد وتحديث السيرفر بالكامل بنجاح (Deploy Complete)!</h2>
            <p style='color: #94a3b8;'>تم تنظيف الكاش، وتنفيذ كافة الـ Migrations، وتشغيل الـ Seed وتحديث الصلاحيات.</p>
            <hr style='border-color: #334155; margin: 20px 0;'>

            <h3 style='color: #93c5fd;'>1. Migrations Output:</h3>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #38bdf8; white-space: pre-wrap;'>" . htmlspecialchars($migrateOut ?: "Nothing to migrate.") . "</pre>

            <h3 style='color: #93c5fd;'>2. Database Seed Output:</h3>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #38bdf8; white-space: pre-wrap;'>" . htmlspecialchars($seedOut ?: "Database seeded successfully.") . "</pre>
        </div>";
    } catch (\Throwable $e) {
        return "<div style='font-family: Arial, sans-serif; padding: 25px; background: #450a0a; color: #fecaca; border-radius: 10px; max-width: 900px; margin: 30px auto;'>
            <h2>❌ خطأ أثناء الـ Deploy:</h2>
            <p><b>السبب:</b> " . htmlspecialchars($e->getMessage()) . "</p>
            <pre style='background: #1e293b; padding: 15px; border-radius: 8px; color: #f87171; white-space: pre-wrap;'>في الملف: " . $e->getFile() . " السطر: " . $e->getLine() . "</pre>
        </div>";
    }
});

Route::get('/module-refresh/{moduleName}', function ($moduleName) {
    Artisan::call('module:disable', ['module' => $moduleName]);
    Artisan::call('module:enable', ['module' => $moduleName]);

    return "Module {$moduleName} refreshed successfully";
});
