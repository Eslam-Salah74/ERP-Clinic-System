<?php

namespace Modules\HR\Services\Expense;

use App\Support\API;
use Illuminate\Support\Facades\Auth;
use Modules\HR\Filters\Expense\ExpenseFilter;
use Modules\HR\Http\Resources\Expense\ExpenseResource;
use Modules\HR\Models\Expense;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Models\Shift;

class ExpenseService
{
    public function index($request, ExpenseFilter $filter)
    {
        $data = Expense::with(['creator', 'shift'])
            ->filter($filter)
            ->latest('expense_date')
            ->paginate(10);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(ExpenseResource::collection($data))->build();
    }

    public function store($request)
    {
        $validated = $request->validated();
        $userId = Auth::id();
        $validated['created_by'] = $userId;

        // ربط تلقائي بالشفت المفتوح للمستخدم لحساب عجز/زيادة الدرج بدقة إن وجد
        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if ($activeShift && empty($validated['shift_id'])) {
            $validated['shift_id'] = $activeShift->id;
        }

        $record = Expense::create($validated);

        return API::newInstance()
            ->isCreated('Expense created successfully')
            ->setData(new ExpenseResource($record->load(['creator', 'shift'])))
            ->build();
    }

    public function show($id)
    {
        $record = Expense::with(['creator', 'shift'])->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new ExpenseResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Expense::findOrFail($id);
        $record->update($request->validated());

        return API::newInstance()
            ->isOk('Updated successfully')
            ->setData(new ExpenseResource($record->load(['creator', 'shift'])))
            ->build();
    }

    public function destroy($id)
    {
        $record = Expense::findOrFail($id);
        $record->delete();

        return API::newInstance()->isOk('Deleted successfully')->build();
    }

    /**
     * ملخص النفقات لحساب الأرباح والخسائر P&L
     */
    public function summary($request)
    {
        $query = Expense::query();

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        $totalExpenses = (float) $query->sum('amount');
        $byCategory = $query->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return API::newInstance()
            ->isOk('Expense summary retrieved successfully')
            ->setData([
                'total_expenses' => $totalExpenses,
                'by_category' => $byCategory,
            ])
            ->build();
    }
}
