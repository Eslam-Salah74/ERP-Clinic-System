<?php

namespace Modules\Setup\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Setup\Enums\PackageItemTypeEnum;
use Modules\Setup\Enums\PackageTypeEnum;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'exists:departments,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', new Enum(PackageTypeEnum::class)],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.item_type' => ['required_with:items', new Enum(PackageItemTypeEnum::class)],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.product_id' => ['nullable', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.custom_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
