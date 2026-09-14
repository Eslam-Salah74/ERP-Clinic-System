<?php

namespace Modules\Reception\Http\Controllers\Api\FollowUp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Reception\Filters\FollowUp\FollowUpFilter;
use Modules\Reception\Http\Requests\FollowUp\StoreFollowUpRequest;
use Modules\Reception\Http\Requests\FollowUp\UpdateFollowUpRequest;
use Modules\Reception\Services\FollowUp\FollowUpService;

class FollowUpController extends Controller implements HasMiddleware
{
    protected $followUp;

    public function __construct(FollowUpService $followUp)
    {
        $this->followUp = $followUp;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read follow_ups', only: ['index']),
            new Middleware('permission:show follow_ups', only: ['show']),
            new Middleware('permission:create follow_ups', only: ['store']),
            new Middleware('permission:update follow_ups', only: ['update', 'changeStatus']),
            new Middleware('permission:delete follow_ups', only: ['destroy']),
        ];
    }

    public function index(Request $request, FollowUpFilter $filter)
    {
        return $this->followUp->index($request, $filter);
    }

    public function store(StoreFollowUpRequest $request)
    {
        return $this->followUp->store($request);
    }

    public function show($followUp)
    {
        return $this->followUp->show($followUp);
    }

    public function update($followUp, UpdateFollowUpRequest $request)
    {
        return $this->followUp->update($followUp, $request);
    }

    public function destroy($followUp)
    {
        return $this->followUp->destroy($followUp);
    }

    public function changeStatus($id, Request $request)
    {
        return $this->followUp->changeStatus($id, $request);
    }
}
