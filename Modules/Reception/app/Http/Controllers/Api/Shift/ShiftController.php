<?php

namespace Modules\Reception\Http\Controllers\Api\Shift;

use App\Http\Controllers\Controller;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use Modules\Reception\Http\Requests\Shift\CloseShiftRequest;
use Modules\Reception\Http\Requests\Shift\OpenShiftRequest;

use Modules\Reception\Services\Shift\ShiftService;

class ShiftController extends Controller implements HasMiddleware
{
    protected $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage shifts', only: ['open', 'close']),
        ];
    }

    public function open(OpenShiftRequest $request)
    {
        return $this->shiftService->openShift($request);
    }

    public function close($id, CloseShiftRequest $request)
    {
        return $this->shiftService->closeShift($id, $request);
    }
}
