<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canTransfer() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_location_id' => ['required', 'uuid', 'exists:locations,id'],
            'to_location_id' => ['required', 'uuid', 'exists:locations,id', 'different:from_location_id'],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'uuid', 'exists:items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.bazar_adjust_type' => ['required', 'in:none,nominal,percentage,manual'],
            'items.*.bazar_adjust_value' => ['required', 'numeric', 'min:0'],
            'items.*.bazar_selling_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
