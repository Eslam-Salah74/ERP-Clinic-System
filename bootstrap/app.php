<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Autoloader fallback for modules (ensures modules load on servers even if composer dump-autoload was not run)
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'Modules\\')) {
        $parts = explode('\\', $class);
        $module = $parts[1] ?? null;
        if ($module) {
            $sub = array_slice($parts, 2);
            if (!empty($sub)) {
                if ($sub[0] === 'Database') {
                    $type = strtolower($sub[1] ?? '');
                    $rest = array_slice($sub, 2);
                    $file = base_path("Modules/{$module}/database/{$type}/" . implode('/', $rest) . '.php');
                } else {
                    $file = base_path("Modules/{$module}/app/" . implode('/', $sub) . '.php');
                }

                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
    }
});

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
