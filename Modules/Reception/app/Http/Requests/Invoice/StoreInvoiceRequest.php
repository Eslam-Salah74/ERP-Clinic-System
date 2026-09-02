<?php

namespace Modules\Reception\Http\Requests\Invoice;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Inventory\Models\Item;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PaymentMethodEnum;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
    
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],

            'type' => ['required', new Enum(InvoiceTypeEnum::class)],
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],

            'doctor_id' => [
                'required_if:type,consultation,session',
                'nullable',
                Rule::exists('users', 'id')->where('type', UserType::DOCTOR->value)
            ],

            'nurse_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('type', UserType::NURSE->value)
            ],

            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:service,product'],

            'items.*.service_id' => ['required_if:items.*.item_type,service', 'nullable', 'exists:services,id'],
            'items.*.product_id' => ['required_if:items.*.item_type,product', 'nullable', 'exists:items,id'],

            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    // --- التحقق الإضافي من توفر المخزن قبل قبول الطلب ---
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                if (($item['item_type'] ?? null) === 'product' && !empty($item['product_id'])) {
                    $product = Item::find($item['product_id']);

                    if ($product) {
                        $requestedQty = $item['quantity'] ?? 0;

                        if ($product->current_stock <= 0) {
                            $validator->errors()->add("items.{$index}.product_id", "عذراً، الصنف ({$product->name}) نفذ من المخزن (رصيده صفر).");
                        } elseif ($requestedQty > $product->current_stock) {
                            $validator->errors()->add("items.{$index}.quantity", "الكمية المطلوبة ({$requestedQty}) أكبر من المتاح في المخزن ({$product->current_stock}) للصنف ({$product->name}).");
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'patient_id.required'        => 'بيانات المريض مطلوبة.',
            'patient_id.exists'          => 'المريض المحدد غير موجود.',
            'type.required'              => 'نوع الفاتورة مطلوب.',
            'payment_method.required'    => 'طريقة الدفع مطلوبة.',
            'doctor_id.required_if'      => 'الطبيب مطلوب لفواتير الكشف والجلسات.',
            'items.required'             => 'يجب إضافة صنف واحد على الأقل.',
            'items.array'                => 'الأصناف يجب أن تكون قائمة.',
            'items.min'                  => 'يجب إضافة صنف واحد على الأقل.',
            'items.*.item_type.required' => 'نوع العنصر مطلوب (service أو product).',
            'items.*.item_type.in'       => 'نوع العنصر يجب أن يكون (service أو product).',
            'items.*.service_id.required_if' => 'الخدمة مطلوبة.',
            'items.*.product_id.required_if' => 'المنتج مطلوب.',
            'items.*.quantity.required'  => 'كمية العنصر مطلوبة.',
            'items.*.quantity.min'       => 'الكمية يجب أن تكون 1 على الأقل.',
        ];
    }
}
