<?php

namespace Modules\HR\Services\Contract;

use App\Support\API;
use Illuminate\Support\Facades\DB;
use Modules\HR\Filters\Contract\StaffContractFilter;
use Modules\HR\Http\Requests\Contract\StoreStaffContractRequest;
use Modules\HR\Http\Requests\Contract\UpdateStaffContractRequest;
use Modules\HR\Http\Resources\Contract\StaffContractResource;
use Modules\HR\Models\StaffContract;

class ContractService
{
    public function index($request, StaffContractFilter $filter)
    {
        $data = StaffContract::with(['user', 'serviceCommissions.service'])
            ->filter($filter)
            ->latest('id')
            ->paginate(15);

        return API::newInstance()
            ->isOk('Contracts retrieved successfully')
            ->setData(StaffContractResource::collection($data))
            ->build();
    }

    public function store(StoreStaffContractRequest $request)
    {
        $validated = $request->validated();
        $serviceCommissions = $validated['service_commissions'] ?? [];
        unset($validated['service_commissions']);

        $contract = DB::transaction(function () use ($validated, $serviceCommissions) {
            $isActive = $validated['is_active'] ?? true;
            $validated['is_active'] = $isActive;

            if ($isActive) {
                StaffContract::where('user_id', $validated['user_id'])->update(['is_active' => false]);
            }

            $contract = StaffContract::create($validated);

            if (!empty($serviceCommissions)) {
                foreach ($serviceCommissions as $comm) {
                    $contract->serviceCommissions()->create([
                        'service_id' => $comm['service_id'],
                        'commission_type' => $comm['commission_type'],
                        'commission_value' => $comm['commission_value'],
                    ]);
                }
            }

            return $contract;
        });

        return API::newInstance()
            ->isCreated('Contract created successfully')
            ->setData(new StaffContractResource($contract->load(['user', 'serviceCommissions.service'])))
            ->build();
    }

    public function show($id)
    {
        $contract = StaffContract::with(['user', 'serviceCommissions.service'])->find($id);
        if (!$contract) {
            return API::newInstance()->isError('Contract not found')->build();
        }

        return API::newInstance()
            ->isOk('Contract retrieved successfully')
            ->setData(new StaffContractResource($contract))
            ->build();
    }

    public function update($id, UpdateStaffContractRequest $request)
    {
        $contract = StaffContract::findOrFail($id);
        $validated = $request->validated();
        $hasCommissions = array_key_exists('service_commissions', $validated);
        $serviceCommissions = $validated['service_commissions'] ?? [];
        unset($validated['service_commissions']);

        $contract = DB::transaction(function () use ($contract, $validated, $hasCommissions, $serviceCommissions) {
            if (isset($validated['is_active']) && $validated['is_active']) {
                StaffContract::where('user_id', $contract->user_id)
                    ->where('id', '!=', $contract->id)
                    ->update(['is_active' => false]);
            }

            $contract->update($validated);

            if ($hasCommissions) {
                $contract->serviceCommissions()->delete();
                foreach ($serviceCommissions as $comm) {
                    $contract->serviceCommissions()->create([
                        'service_id' => $comm['service_id'],
                        'commission_type' => $comm['commission_type'],
                        'commission_value' => $comm['commission_value'],
                    ]);
                }
            }

            return $contract;
        });

        return API::newInstance()
            ->isOk('Contract updated successfully')
            ->setData(new StaffContractResource($contract->load(['user', 'serviceCommissions.service'])))
            ->build();
    }

    public function destroy($id)
    {
        $contract = StaffContract::findOrFail($id);
        $contract->delete();

        return API::newInstance()->isOk('Contract deleted successfully')->build();
    }

    public function getActiveContractByUser($userId)
    {
        $contract = StaffContract::with(['user', 'serviceCommissions.service'])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$contract) {
            return API::newInstance()->isError('No active contract found for this user')->build();
        }

        return API::newInstance()
            ->isOk('Active contract retrieved successfully')
            ->setData(new StaffContractResource($contract))
            ->build();
    }
}
