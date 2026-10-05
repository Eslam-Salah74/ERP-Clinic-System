<?php

namespace Modules\HR\Services\Contract;

use App\Support\API;
use Illuminate\Support\Facades\DB;
use Modules\HR\Enums\CommissionTypeEnum;
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

        $commType = $validated['medication_commission_type'] ?? CommissionTypeEnum::PERCENTAGE->value;
        if ($commType === CommissionTypeEnum::FIXED->value || $commType === CommissionTypeEnum::FIXED) {
            $validated['medication_commission_type'] = CommissionTypeEnum::FIXED->value;
            $validated['medication_commission_value'] = (float) ($validated['medication_commission_value'] ?? 0);
            $validated['medication_sales_percentage'] = 0;
        } else {
            $val = (float) ($validated['medication_commission_value'] ?? $validated['medication_sales_percentage'] ?? 0);
            $validated['medication_commission_type'] = CommissionTypeEnum::PERCENTAGE->value;
            $validated['medication_commission_value'] = $val;
            $validated['medication_sales_percentage'] = $val;
        }

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
                        'doctor_service_price' => $comm['doctor_service_price'] ?? 0,
                        'is_laser' => (bool) ($comm['is_laser'] ?? false),
                        'commission_type' => $comm['commission_type'],
                        'commission_value' => $comm['commission_value'],
                        'target_commission_value' => isset($comm['target_commission_value']) && $comm['target_commission_value'] !== null ? $comm['target_commission_value'] : null,
                    ]);
                }
            }

            return $contract;
        });

        return API::newInstance()
            ->isCreated('Contract created successfully')
            ->setData(new StaffContractResource($contract->refresh()->load(['user', 'serviceCommissions.service'])))
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

        if (array_key_exists('medication_commission_type', $validated) || array_key_exists('medication_commission_value', $validated) || array_key_exists('medication_sales_percentage', $validated)) {
            $rawCommType = $validated['medication_commission_type'] ?? $contract->medication_commission_type;
            $commType = $rawCommType instanceof \BackedEnum ? $rawCommType->value : ($rawCommType ?? CommissionTypeEnum::PERCENTAGE->value);

            if ($commType === CommissionTypeEnum::FIXED->value || $commType === CommissionTypeEnum::FIXED) {
                $validated['medication_commission_type'] = CommissionTypeEnum::FIXED->value;
                $validated['medication_commission_value'] = (float) ($validated['medication_commission_value'] ?? $contract->medication_commission_value ?? 0);
                $validated['medication_sales_percentage'] = 0;
            } else {
                $val = (float) ($validated['medication_commission_value'] ?? $validated['medication_sales_percentage'] ?? $contract->medication_commission_value ?? $contract->medication_sales_percentage ?? 0);
                $validated['medication_commission_type'] = CommissionTypeEnum::PERCENTAGE->value;
                $validated['medication_commission_value'] = $val;
                $validated['medication_sales_percentage'] = $val;
            }
        }

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
                        'doctor_service_price' => $comm['doctor_service_price'] ?? 0,
                        'is_laser' => (bool) ($comm['is_laser'] ?? false),
                        'commission_type' => $comm['commission_type'],
                        'commission_value' => $comm['commission_value'],
                        'target_commission_value' => isset($comm['target_commission_value']) && $comm['target_commission_value'] !== null ? $comm['target_commission_value'] : null,
                    ]);
                }
            }

            return $contract;
        });

        return API::newInstance()
            ->isOk('Contract updated successfully')
            ->setData(new StaffContractResource($contract->refresh()->load(['user', 'serviceCommissions.service'])))
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
