<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
            'model_id' => ['required', 'uuid', 'exists:product_models,id'],
            'color_id' => ['required', 'uuid', 'exists:colors,id'],
            'size_id' => ['required', 'uuid', 'exists:sizes,id'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
