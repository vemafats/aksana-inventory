<?php

namespace App\Http\Requests\StockIn;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canStockIn() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'photo_id' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barcode' => ['required', 'string'],
            'items.*.qty_received' => ['required', 'integer', 'min:1'],
            'items.*.qty_available' => ['required', 'integer', 'min:0'],
            'items.*.qty_damaged' => ['required', 'integer', 'min:0'],
            'items.*.supplier_cost' => ['required', 'numeric', 'min:0'],
            'items.*.base_margin_type' => ['required', 'in:none,nominal,percentage'],
            'items.*.base_margin_value' => ['required', 'numeric', 'min:0'],
            'items.*.base_selling_price' => ['required', 'numeric', 'min:0'],
            'items.*.qc_note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $received = (int) ($item['qty_received'] ?? 0);
                $available = (int) ($item['qty_available'] ?? 0);
                $damaged = (int) ($item['qty_damaged'] ?? 0);
                $barcode = $item['barcode'] ?? "index {$index}";

                if ($received !== $available + $damaged) {
                    $validator->errors()->add(
                        "items.{$index}.qty_received",
                        "qty_received harus sama dengan qty_available + qty_damaged untuk item {$barcode}"
                    );
                }
            }
        });
    }
}
