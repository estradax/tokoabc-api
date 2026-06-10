<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : $product;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug,'.$productId],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku,'.$productId],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'media' => ['nullable', 'array'],
            'media.*.url' => ['required', 'string'],
            'media.*.type' => ['nullable', 'string', 'in:image,video'],
            'media.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
