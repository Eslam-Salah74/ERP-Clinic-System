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

            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:service,product,package,package_consumption,package_debt_payment'],

            'items.*.service_id' => [
                'required_if:items.*.item_type,service',
                'prohibited_if:items.*.item_type,product,package,package_debt_payment',
                'nullable',
                'exists:services,id'
            ],
            'items.*.service_items_ids' => [
                'prohibited_if:items.*.item_type,product,package,package_debt_payment',
                'nullable',
                'array'
            ],
            'items.*.service_items_ids.*' => [
                'integer',
                'exists:items,id'
            ],
            'items.*.product_id' => [
                'required_if:items.*.item_type,product',
                'prohibited_if:items.*.item_type,service,package,package_consumption,package_debt_payment',
                'nullable',
                'exists:items,id'
            ],

            'items.*.package_id' => [
                'required_if:items.*.item_type,package',
                'nullable',
                'exists:packages,id'
            ],

            'items.*.patient_package_balance_id' => [
                'required_if:items.*.item_type,package_consumption',
                'nullable',
                'exists:patient_package_balances,id'
            ],

            'items.*.patient_package_id' => [
                'required_if:items.*.item_type,package_debt_payment',
                'nullable',
                'exists:patient_packages,id'
            ],

            'items.*.amount' => [
                'required_if:items.*.item_type,package_debt_payment',
                'nullable',
                'numeric',
                'min:0.01'
            ],

            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    // --- التحقق الإضافي من توفر المخزن قبل قبول الطلب ---
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $itemType = $item['item_type'] ?? null;

                if ($itemType === 'product' && !empty($item['product_id'])) {
                    $product = Item::find($item['product_id']);

                    if ($product) {
                        $requestedQty = $item['quantity'] ?? 0;

                        if ($product->current_stock <= 0) {
                            $validator->errors()->add("items.{$index}.product_id", "عذراً، الصنف ({$product->name}) نفذ من المخزن (رصيده صفر).");
                        } elseif ($requestedQty > $product->current_stock) {
                            $validator->errors()->add("items.{$index}.quantity", "الكمية المطلوبة ({$requestedQty}) أكبر من المتاح في المخزن ({$product->current_stock}) للصنف ({$product->name}).");
                        }
                    }
                } elseif ($itemType === 'package_consumption' && !empty($item['patient_package_balance_id'])) {
                    $balance = \Modules\Reception\Models\PatientPackageBalance::with('patientPackage')->find($item['patient_package_balance_id']);
                    if ($balance) {
                        if ($balance->patientPackage && $balance->patientPackage->patient_id != $this->input('patient_id')) {
                            $validator->errors()->add("items.{$index}.patient_package_balance_id", "رصيد الباقة المحدد لا يتبع لهذا المريض.");
                        }
                        if ($balance->patientPackage && $balance->patientPackage->status->value !== 'active') {
                            $validator->errors()->add("items.{$index}.patient_package_balance_id", "هذه الباقة غير نشطة أو مكتملة ولا يمكن الاستهلاك منها.");
                        }
                        $requestedQty = (float) ($item['quantity'] ?? 1);
                        if ($requestedQty > (float) $balance->remaining_quantity) {
                            $name = $balance->custom_name ?? ($balance->service ? $balance->service->name : 'الرصيد');
                            $validator->errors()->add("items.{$index}.quantity", "الرصيد المتبقي للبند ({$name}) هو ({$balance->remaining_quantity})، لا يمكن استهلاك ({$requestedQty}).");
                        }
                    }
                } elseif ($itemType === 'package_debt_payment' && !empty($item['patient_package_id'])) {
                    $pkg = \Modules\Reception\Models\PatientPackage::find($item['patient_package_id']);
                    if ($pkg) {
                        if ($pkg->patient_id != $this->input('patient_id')) {
                            $validator->errors()->add("items.{$index}.patient_package_id", "هذه الباقة لا تخص هذا المريض.");
                        }
                        if ((float) $pkg->remaining_amount <= 0) {
                            $validator->errors()->add("items.{$index}.patient_package_id", "هذه الباقة مسددة بالكامل ولا توجد عليها أي مديونية.");
                        }
                        $payAmount = (float) ($item['amount'] ?? 0);
                        if ($payAmount > (float) $pkg->remaining_amount) {
                            $validator->errors()->add("items.{$index}.amount", "المبلغ المراد سداده ({$payAmount} ج) أكبر من إجمالي المتبقي على الباقة ({$pkg->remaining_amount} ج).");
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
            'items.*.service_id.required_if' => 'الخدمة مطلوبة عند اختيار نوع الخدمة.',
            'items.*.service_id.prohibited_if' => 'لا يمكن إضافة service_id مع منتج.',
            'items.*.product_id.required_if' => 'المنتج مطلوب عند اختيار نوع المنتج.',
            'items.*.product_id.prohibited_if' => 'لا يمكن إضافة product_id مع خدمة.',
            'items.*.quantity.required'  => 'كمية العنصر مطلوبة.',
            'items.*.quantity.min'       => 'الكمية يجب أن تكون 1 على الأقل.',
        ];
    }
}
