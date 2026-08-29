<?php

namespace Modules\Setup\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Setup\Services\Setting\SettingService;
use Modules\Setup\Filters\Setting\SettingFilter;
use Modules\Setup\Http\Requests\Setting\StoreSettingRequest;
use Modules\Setup\Http\Requests\Setting\UpdateSettingRequest;

class SettingController extends Controller implements HasMiddleware
{
    protected $setting;

    public function __construct(SettingService $setting)
    {
        $this->setting = $setting;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read settings', only: ['index']),
            new Middleware('permission:show settings', only: ['show']),
            new Middleware('permission:create settings', only: ['store']),
            new Middleware('permission:update settings', only: ['update']),
            new Middleware('permission:delete settings', only: ['destroy']),
        ];
    }

    public function index(Request $request, SettingFilter $filter)
    {
        return $this->setting->index($request, $filter);
    }

    public function store(StoreSettingRequest $request)
    {
        return $this->setting->store($request);
    }

    public function show($setting)
    {
        return $this->setting->show($setting);
    }

    public function update($setting, UpdateSettingRequest $request)
    {
        return $this->setting->update($setting, $request);
    }

    public function destroy($setting)
    {
        return $this->setting->destroy($setting);
    }
}