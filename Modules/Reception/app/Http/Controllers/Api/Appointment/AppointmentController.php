<?php

namespace Modules\Reception\Http\Controllers\Api\Appointment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Reception\Filters\Appointment\AppointmentFilter;
use Modules\Reception\Http\Requests\Appointment\ChangeAppointmentStatusRequest;
use Modules\Reception\Http\Requests\Appointment\StoreAppointmentRequest;
use Modules\Reception\Http\Requests\Appointment\UpdateAppointmentRequest;
use Modules\Reception\Services\Appointment\AppointmentService;

class AppointmentController extends Controller implements HasMiddleware
{
    protected $appointment;

    public function __construct(AppointmentService $appointment)
    {
        $this->appointment = $appointment;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read appointments', only: ['index']),
            new Middleware('permission:show appointments', only: ['show']),
            new Middleware('permission:create appointments', only: ['store']),
            new Middleware('permission:update appointments', only: ['update']),
            new Middleware('permission:delete appointments', only: ['destroy']),
        ];
    }

    public function index(Request $request, AppointmentFilter $filter)
    {
        return $this->appointment->index($request, $filter);
    }

    public function store(StoreAppointmentRequest $request)
    {
        return $this->appointment->store($request);
    }

    public function show($appointment)
    {
        return $this->appointment->show($appointment);
    }

    public function update($appointment, UpdateAppointmentRequest $request)
    {
        return $this->appointment->update($appointment, $request);
    }

    public function destroy($appointment)
    {
        return $this->appointment->destroy($appointment);
    }

    public function changeStatus($id, ChangeAppointmentStatusRequest $request)
    {
        return $this->appointment->changeStatus($id, $request);
    }
}
