<?php

namespace Modules\Inventory\Services\PurchaseInvoice;

use App\Support\API;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Filters\PurchaseInvoice\PurchaseInvoiceFilter;
use Modules\Inventory\Http\Resources\PurchaseInvoice\PurchaseInvoiceResource;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\PurchaseInvoiceItem;

class PurchaseInvoiceService
{
    public function index($request, PurchaseInvoiceFilter $filter)
    {
        $data = PurchaseInvoice::with(['supplier', 'items.item'])->filter($filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(PurchaseInvoiceResource::collection($data))->build();
    }

    public function store($requestOrData)
    {
        return DB::transaction(function () use ($requestOrData) {
            $validated = is_object($requestOrData) && method_exists($requestOrData, 'validated')
                ? $requestOrData->validated()
                : $requestOrData;

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $totalAmount += $itemData['quantity'] * $itemData['purchase_price'];
            }

            $invoice = PurchaseInvoice::create([
                'supplier_id' => $validated['supplier_id'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $quantity = $itemData['quantity'];
                $price = $itemData['purchase_price'];
                $itemId = $itemData['item_id'];

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'purchase_price' => $price,
                    'total_price' => $quantity * $price,
                ]);

                // جلب المنتج لحساب الكمية بالمخزن بناءً على معامل التحويل
                $item = Item::findOrFail($itemId);

                // الكمية المضافة للمخزن = الكمية في الفاتورة × معامل التحويل
                // مثلاً: لو اشترينا 2 زجاجة (bottle)، ومعامل التحويل 5 (مل)، سيضاف للمخزن 10 (مل)
                $stockQuantityToAdd = $quantity * $item->conversion_factor;

                // تحديث رصيد المخزن تلقائياً بالوحدة الصغرى
                $item->increment('current_stock', $stockQuantityToAdd);
            }

            return API::newInstance()
                ->isCreated('Purchase invoice created successfully')
                ->setData(new PurchaseInvoiceResource($invoice->load('items.item', 'supplier')))
                ->build();
        });
    }

    public function show($id)
    {
        $record = PurchaseInvoice::with(['supplier', 'items.item'])->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new PurchaseInvoiceResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = PurchaseInvoice::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new PurchaseInvoiceResource($record->load('items.item', 'supplier')))->build();
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = PurchaseInvoice::with('items')->findOrFail($id);

            // عند حذف الفاتورة، يتم خصم الكمية المحسوبة بالمخزن بناءً على معامل التحويل للحفاظ على دقة المخزون
            foreach ($record->items as $invoiceItem) {
                $item = Item::find($invoiceItem->item_id);
                if ($item) {
                    $stockQuantityToSubtract = $invoiceItem->quantity * $item->conversion_factor;
                    $item->decrement('current_stock', $stockQuantityToSubtract);
                }
            }

            $record->delete();
            return API::newInstance()->isOk('Deleted successfully')->build();
        });
    }
}
