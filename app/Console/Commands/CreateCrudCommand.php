<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateCrudCommand extends Command
{
    protected $signature = 'create:crud';
    protected $description = 'Generates a fully-structured CRUD with nested folders for everything and clean Controllers for Laravel 12.';

    public function handle()
    {
        $modulesPath = base_path('Modules');
        if (!File::exists($modulesPath)) {
            $this->error('The Modules directory does not exist!');
            return;
        }

        $modules = array_map('basename', File::directories($modulesPath));

        if (empty($modules)) {
            $this->error('No modules found! Please create a module first.');
            return;
        }

        $this->info('Available Modules:');
        foreach ($modules as $index => $moduleName) {
            $this->line(" [{$index}] {$moduleName}");
        }

        $moduleIndex = $this->ask('Enter the number of the module where you want to add the CRUD');

        if (!isset($modules[$moduleIndex])) {
            $this->error('Invalid module selection!');
            return;
        }

        $selectedModule = $modules[$moduleIndex];

        $crudName = $this->anticipate('Enter the CRUD name (in singular form, e.g., Employee, Patient):', ['Employee', 'Patient', 'Service']);

        if (empty($crudName)) {
            $this->error('The CRUD name is required!');
            return;
        }

        $modelName = Str::studly(Str::singular($crudName));
        $tableName = Str::snake(Str::plural($crudName));
        $permissionName = Str::snake($tableName);
        $lowerName = Str::camel($modelName);

        $moduleBasePath = base_path("Modules/{$selectedModule}");

        // --- 1. Create Migration ---
        $migrationsDir = "{$moduleBasePath}/Database/migrations";
        if (!File::exists($migrationsDir)) {
            $migrationsDir = "{$moduleBasePath}/database/migrations";
        }
        File::ensureDirectoryExists($migrationsDir);

        $migrationName = date('Y_m_d_His') . "_create_{$tableName}_table.php";
        $migrationPath = "{$migrationsDir}/{$migrationName}";

        $migrationContent = "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};";
        File::put($migrationPath, $migrationContent);
        $this->info("✔ Migration created.");

        // --- 2. Create Model ---
        $modelsDir = "{$moduleBasePath}/app/Models";
        File::ensureDirectoryExists($modelsDir);
        $modelPath = "{$modelsDir}/{$modelName}.php";

        $modelContent = "<?php

namespace Modules\\{$selectedModule}\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

class {$modelName} extends Model
{
    use HasFactory, SoftDeletes;

    protected \$table = '{$tableName}';
    protected \$guarded = ['id'];
}";
        File::put($modelPath, $modelContent);
        $this->info("✔ Model created.");

        // --- 3. Create Filter Class inside a dedicated sub-folder ---
        $filtersDir = "{$moduleBasePath}/app/Filters/{$modelName}";
        File::ensureDirectoryExists($filtersDir);
        $filterPath = "{$filtersDir}/{$modelName}Filter.php";

        $filterContent = "<?php

namespace Modules\\{$selectedModule}\\Filters\\{$modelName};

use App\\Filters\\Filters;

class {$modelName}Filter extends Filters
{
    protected \$var_filters = [
        // Add filterable variables here
    ];
}";
        File::put($filterPath, $filterContent);
        $this->info("✔ Filter class created inside '{$modelName}' folder.");

        // --- 4. Create Form Requests inside a sub-folder ---
        $requestsDir = "{$moduleBasePath}/app/Http/Requests/{$modelName}";
        File::ensureDirectoryExists($requestsDir);

        $storeRequestPath = "{$requestsDir}/Store{$modelName}Request.php";
        $storeRequestContent = "<?php

namespace Modules\\{$selectedModule}\\Http\\Requests\\{$modelName};

use Illuminate\\Foundation\\Http\\FormRequest;

class Store{$modelName}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Define validation rules for store
        ];
    }
}";
        File::put($storeRequestPath, $storeRequestContent);

        $updateRequestPath = "{$requestsDir}/Update{$modelName}Request.php";
        $updateRequestContent = "<?php

namespace Modules\\{$selectedModule}\\Http\\Requests\\{$modelName};

use Illuminate\\Foundation\\Http\\FormRequest;

class Update{$modelName}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Define validation rules for update
        ];
    }
}";
        File::put($updateRequestPath, $updateRequestContent);
        $this->info("✔ Form Requests created inside '{$modelName}' folder.");

        // --- 5. Create API Resource inside a sub-folder ---
        $resourcesDir = "{$moduleBasePath}/app/Http/Resources/{$modelName}";
        File::ensureDirectoryExists($resourcesDir);
        $resourcePath = "{$resourcesDir}/{$modelName}Resource.php";

        $resourceContent = "<?php

namespace Modules\\{$selectedModule}\\Http\\Resources\\{$modelName};

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

class {$modelName}Resource extends JsonResource
{
    public function toArray(Request \$request): array
    {
        return [
            // Resource fields
        ];
    }
}";
        File::put($resourcePath, $resourceContent);
        $this->info("✔ API Resource created inside '{$modelName}' folder.");

        // --- 6. Create Service Class inside a sub-folder ---
        $servicesDir = "{$moduleBasePath}/app/Services/{$modelName}";
        File::ensureDirectoryExists($servicesDir);
        $servicePath = "{$servicesDir}/{$modelName}Service.php";

        $serviceContent = "<?php

namespace Modules\\{$selectedModule}\\Services\\{$modelName};

use Modules\\{$selectedModule}\\Models\\{$modelName};
use Modules\\{$selectedModule}\\Filters\\{$modelName}\\{$modelName}Filter;
use Modules\\{$selectedModule}\\Http\\Resources\\{$modelName}\\{$modelName}Resource;
use App\\Support\\API;

class {$modelName}Service
{
    public function index(\$request, {$modelName}Filter \$filter)
    {
        \$data = {$modelName}::filter(\$filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData({$modelName}Resource::collection(\$data))->build();
    }

    public function store(\$request)
    {
        \$data = {$modelName}::create(\$request->validated());
        return API::newInstance()->isCreated('Created successfully')->setData(new {$modelName}Resource(\$data))->build();
    }

    public function show(\$id)
    {
        \$record = {$modelName}::find(\$id);
        if (!\$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new {$modelName}Resource(\$record))->build();
    }

    public function update(\$id, \$request)
    {
        \$record = {$modelName}::findOrFail(\$id);
        \$record->update(\$request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new {$modelName}Resource(\$record))->build();
    }

    public function destroy(\$id)
    {
        \$record = {$modelName}::findOrFail(\$id);
        \$record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}";
        File::put($servicePath, $serviceContent);
        $this->info("✔ Service class created inside '{$modelName}' folder.");

        // --- 7. Create Controller inside Api/{$modelName} folder compatible with Laravel 12 (HasMiddleware) ---
        $controllersDir = "{$moduleBasePath}/app/Http/Controllers/Api/{$modelName}";
        File::ensureDirectoryExists($controllersDir);
        $controllerPath = "{$controllersDir}/{$modelName}Controller.php";

        $controllerContent = "<?php

namespace Modules\\{$selectedModule}\\Http\\Controllers\\Api\\{$modelName};

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Routing\\Controllers\\HasMiddleware;
use Illuminate\\Routing\\Controllers\\Middleware;
use Modules\\{$selectedModule}\\Services\\{$modelName}\\{$modelName}Service;
use Modules\\{$selectedModule}\\Filters\\{$modelName}\\{$modelName}Filter;
use Modules\\{$selectedModule}\\Http\\Requests\\{$modelName}\\Store{$modelName}Request;
use Modules\\{$selectedModule}\\Http\\Requests\\{$modelName}\\Update{$modelName}Request;

class {$modelName}Controller extends Controller implements HasMiddleware
{
    protected \${$lowerName};

    public function __construct({$modelName}Service \${$lowerName})
    {
        \$this->{$lowerName} = \${$lowerName};
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read {$permissionName}', only: ['index']),
            new Middleware('permission:show {$permissionName}', only: ['show']),
            new Middleware('permission:create {$permissionName}', only: ['store']),
            new Middleware('permission:update {$permissionName}', only: ['update']),
            new Middleware('permission:delete {$permissionName}', only: ['destroy']),
        ];
    }

    public function index(Request \$request, {$modelName}Filter \$filter)
    {
        return \$this->{$lowerName}->index(\$request, \$filter);
    }

    public function store(Store{$modelName}Request \$request)
    {
        return \$this->{$lowerName}->store(\$request);
    }

    public function show(\${$lowerName})
    {
        return \$this->{$lowerName}->show(\${$lowerName});
    }

    public function update(\${$lowerName}, Update{$modelName}Request \$request)
    {
        return \$this->{$lowerName}->update(\${$lowerName}, \$request);
    }

    public function destroy(\${$lowerName})
    {
        return \$this->{$lowerName}->destroy(\${$lowerName});
    }
}";
        File::put($controllerPath, $controllerContent);
        $this->info("✔ Controller created inside Api/{$modelName} folder.");

        // --- 8. Create Seeders inside a sub-folder ---
        $seedersDir = "{$moduleBasePath}/Database/seeders/{$modelName}";
        if (!File::exists($seedersDir)) {
            $seedersDir = "{$moduleBasePath}/database/seeders/{$modelName}";
        }
        File::ensureDirectoryExists($seedersDir);

        // أ. سيدر الصلاحيات
        $permissionSeederName = "{$modelName}PermissionDatabaseSeeder";
        $permissionSeederPath = "{$seedersDir}/{$permissionSeederName}.php";
        $permissionSeederContent = "<?php

namespace Modules\\{$selectedModule}\\Database\\Seeders\\{$modelName};

use Illuminate\\Database\\Seeder;

class {$permissionSeederName} extends Seeder
{
    use \\App\\Traits\\PermissionSeederTrait;

    public function run(): void
    {
        \$actions = ['read', 'create', 'show', 'update', 'delete'];
        \$models = [
            '{$permissionName}' => '{$selectedModule}',
        ];

        \$this->createOrUpdatePermissions(\$models, \$actions);
    }
}";
        File::put($permissionSeederPath, $permissionSeederContent);

        // ب. سيدر البيانات
        $dataSeederName = "{$modelName}DatabaseSeeder";
        $dataSeederPath = "{$seedersDir}/{$dataSeederName}.php";
        $dataSeederContent = "<?php

namespace Modules\\{$selectedModule}\\Database\\Seeders\\{$modelName};

use Illuminate\\Database\\Seeder;
use Modules\\{$selectedModule}\\Models\\{$modelName};

class {$dataSeederName} extends Seeder
{
    public function run(): void
    {
        // Add initial data here if needed
    }
}";
        File::put($dataSeederPath, $dataSeederContent);
        $this->info("✔ Both Seeders created inside '{$modelName}' folder.");

        // --- 9. Automatically Register Seeders in Module's Main Seeder correctly ---
        $mainSeedersDir = "{$moduleBasePath}/Database/seeders";
        if (!File::exists($mainSeedersDir)) {
            $mainSeedersDir = "{$moduleBasePath}/database/seeders";
        }
        $mainSeederFiles = glob("{$mainSeedersDir}/*DatabaseSeeder.php");

        foreach ($mainSeederFiles as $mainFile) {
            // تجاهل السيدرات الفرعية الداخلية ونركز على السيدر الرئيسي للموديول
            if (str_contains($mainFile, $modelName)) {
                continue;
            }

            $content = File::get($mainFile);
            $useStatements = "use Modules\\{$selectedModule}\\Database\\Seeders\\{$modelName}\\{$permissionSeederName};\nuse Modules\\{$selectedModule}\\Database\\Seeders\\{$modelName}\\{$dataSeederName};";

            if (!str_contains($content, $permissionSeederName)) {
                $content = preg_replace(
                    '/(namespace\s+[^;]+;)/',
                    "$1\n\n{$useStatements}",
                    $content
                );

                // تم تعديل طريقة كتابة الأوامر بشكل سليم ومنفصل لتجنب أخطاء السنتكس
                $callsToAdd = "\$this->call({$permissionSeederName}::class);\n        \$this->call({$dataSeederName}::class);";
                $content = preg_replace(
                    '/(public function run\(\): void\s*\{)/',
                    "$1\n        {$callsToAdd}",
                    $content
                );

                File::put($mainFile, $content);
            }
        }

        $this->info("✨ Clean structured CRUD for [{$modelName}] inside module [{$selectedModule}] generated successfully!");
    }
}
