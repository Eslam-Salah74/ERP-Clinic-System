<?php

namespace Modules\HR\Services\CommissionTemplate;

use App\Support\API;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Requests\CommissionTemplate\StoreCommissionTemplateRequest;
use Modules\HR\Http\Requests\CommissionTemplate\UpdateCommissionTemplateRequest;
use Modules\HR\Http\Resources\CommissionTemplate\CommissionTemplateResource;
use Modules\HR\Models\CommissionTemplate;

class CommissionTemplateService
{
    public function index(Request $request)
    {
        $query = CommissionTemplate::with(['department', 'creator', 'items.service'])
            ->withCount('items');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $templates = $request->has('per_page') && $request->per_page == -1
            ? $query->latest('id')->get()
            : $query->latest('id')->paginate($request->get('per_page', 15));

        return API::newInstance()
            ->isOk('Commission templates retrieved successfully')
            ->setData(CommissionTemplateResource::collection($templates))
            ->build();
    }

    public function store(StoreCommissionTemplateRequest $request)
    {
        $validated = $request->validated();
        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $template = DB::transaction(function () use ($validated, $items) {
            $template = CommissionTemplate::create($validated);

            foreach ($items as $item) {
                $template->items()->create([
                    'service_id' => $item['service_id'],
                    'doctor_service_price' => $item['doctor_service_price'] ?? 0,
                    'is_laser' => (bool) ($item['is_laser'] ?? false),
                    'commission_type' => $item['commission_type'],
                    'commission_value' => $item['commission_value'],
                    'target_commission_value' => $item['target_commission_value'] ?? null,
                ]);
            }

            return $template;
        });

        return API::newInstance()
            ->isCreated('Commission template created successfully')
            ->setData(new CommissionTemplateResource($template->load(['department', 'creator', 'items.service'])))
            ->build();
    }

    public function show($id)
    {
        $template = CommissionTemplate::with(['department', 'creator', 'items.service'])
            ->withCount('items')
            ->find($id);

        if (!$template) {
            return API::newInstance()->isError('Commission template not found')->build();
        }

        return API::newInstance()
            ->isOk('Commission template retrieved successfully')
            ->setData(new CommissionTemplateResource($template))
            ->build();
    }

    public function update($id, UpdateCommissionTemplateRequest $request)
    {
        $template = CommissionTemplate::findOrFail($id);
        $validated = $request->validated();

        $hasItems = array_key_exists('items', $validated);
        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $template = DB::transaction(function () use ($template, $validated, $hasItems, $items) {
            $template->update($validated);

            if ($hasItems) {
                $template->items()->delete();
                foreach ($items as $item) {
                    $template->items()->create([
                        'service_id' => $item['service_id'],
                        'doctor_service_price' => $item['doctor_service_price'] ?? 0,
                        'is_laser' => (bool) ($item['is_laser'] ?? false),
                        'commission_type' => $item['commission_type'],
                        'commission_value' => $item['commission_value'],
                        'target_commission_value' => $item['target_commission_value'] ?? null,
                    ]);
                }
            }

            return $template;
        });

        return API::newInstance()
            ->isOk('Commission template updated successfully')
            ->setData(new CommissionTemplateResource($template->load(['department', 'creator', 'items.service'])))
            ->build();
    }

    public function destroy($id)
    {
        $template = CommissionTemplate::findOrFail($id);
        $template->delete();

        return API::newInstance()->isOk('Commission template deleted successfully')->build();
    }
}

