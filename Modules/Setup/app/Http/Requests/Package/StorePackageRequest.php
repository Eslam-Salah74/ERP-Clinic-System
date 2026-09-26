<?php

namespace Modules\Setup\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Setup\Enums\PackageItemTypeEnum;
use Modules\Setup\Enums\PackageTypeEnum;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(PackageTypeEnum::class)],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', new Enum(PackageItemTypeEnum::class)],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.product_id' => ['nullable', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.custom_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
