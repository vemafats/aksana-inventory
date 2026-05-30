<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canSell() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'transaction_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,qris,transfer'],
            'note' => ['nullable', 'string'],
            'photo_id' => ['nullable', 'uuid'],
            'transaction_discount_type' => ['required', 'in:none,nominal,percentage'],
            'transaction_discount_value' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'uuid', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.item_discount_type' => ['required', 'in:none,nominal,percentage'],
            'items.*.item_discount_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}
