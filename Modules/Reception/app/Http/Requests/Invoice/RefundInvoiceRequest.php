<?php

namespace Modules\Reception\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class RefundInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // لو true يبقى هيرجع الفاتورة كلها، لو false يبقى هيبعت array من العناصر المعينة
            'is_full_refund' => ['required', 'boolean'],

            // لو الاسترداد جزئي، لازم يبعت الـ items اللي هترجع
            'items' => ['required_if:is_full_refund,false', 'array'],
            'items.*.invoice_item_id' => ['required_with:items', 'exists:invoice_items,id'],
            'items.*.return_quantity' => ['required_with:items', 'integer', 'min:1'],
        ];
    }
}
