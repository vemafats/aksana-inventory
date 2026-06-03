<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canReturnStock() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('return_date') && ! $this->has('transfer_date')) {
            $this->merge([
                'transfer_date' => $this->input('return_date'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'from_location_id' => ['sometimes', 'nullable', 'uuid', 'exists:locations,id'],
            'return_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'uuid', 'exists:items,id'],
            'items.*.qty_good' => ['required', 'integer', 'min:0'],
            'items.*.qty_damaged' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);

            $hasQuantity = collect($items)->contains(
                fn (mixed $item): bool => is_array($item)
                    && ((int) ($item['qty_good'] ?? 0)) + ((int) ($item['qty_damaged'] ?? 0)) > 0,
            );

            if (! $hasQuantity) {
                $validator->errors()->add(
                    'items',
                    'Minimal satu item harus memiliki qty good atau damaged lebih dari 0.',
                );
            }
        });
    }
}
