<?php

namespace Modules\HR\Http\Controllers\Api\Expense;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\HR\Filters\Expense\ExpenseFilter;
use Modules\HR\Http\Requests\Expense\StoreExpenseRequest;
use Modules\HR\Http\Requests\Expense\UpdateExpenseRequest;
use Modules\HR\Services\Expense\ExpenseService;

class ExpenseController extends Controller implements HasMiddleware
{
    protected $expense;

    public function __construct(ExpenseService $expense)
    {
        $this->expense = $expense;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read expenses', only: ['index', 'summary']),
            new Middleware('permission:show expenses', only: ['show']),
            new Middleware('permission:create expenses', only: ['store']),
            new Middleware('permission:update expenses', only: ['update']),
            new Middleware('permission:delete expenses', only: ['destroy']),
        ];
    }

    public function index(Request $request, ExpenseFilter $filter)
    {
        return $this->expense->index($request, $filter);
    }

    public function store(StoreExpenseRequest $request)
    {
        return $this->expense->store($request);
    }

    public function show($expense)
    {
        return $this->expense->show($expense);
    }

    public function update($expense, UpdateExpenseRequest $request)
    {
        return $this->expense->update($expense, $request);
    }

    public function destroy($expense)
    {
        return $this->expense->destroy($expense);
    }

    public function summary(Request $request)
    {
        return $this->expense->summary($request);
    }
}
