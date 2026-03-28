<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Resources\AttributeResource;
use App\Modules\Catalog\Services\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeController extends Controller
{
    public function __construct(
        private readonly AttributeService $attributeService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return AttributeResource::collection(
            $this->attributeService->listAttributes()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:attributes,slug', 'regex:/^[a-z0-9-]+$/'],
            'attribute_type' => ['sometimes', 'string', 'in:color,size,material,brand,custom'],
            'input_type' => ['sometimes', 'string', 'in:dropdown,color_picker,text,radio'],
            'is_filterable' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $attribute = $this->attributeService->createAttribute($data);

        return (new AttributeResource($attribute->load('values')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Attribute $attribute): AttributeResource
    {
        return new AttributeResource($attribute->load('values'));
    }

    public function update(Request $request, Attribute $attribute): AttributeResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:attributes,slug,'.$attribute->id, 'regex:/^[a-z0-9-]+$/'],
            'attribute_type' => ['sometimes', 'string', 'in:color,size,material,brand,custom'],
            'input_type' => ['sometimes', 'string', 'in:dropdown,color_picker,text,radio'],
            'is_filterable' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $updated = $this->attributeService->updateAttribute($attribute, $data);

        return new AttributeResource($updated);
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        $attribute->values()->delete();
        $attribute->delete();

        return response()->json(null, 204);
    }

    public function storeValue(Request $request, Attribute $attribute): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'value_key' => ['required', 'string', 'max:100', 'unique:attribute_values,value_key,NULL,id,attribute_id,'.$attribute->id],
            'display_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $value = $this->attributeService->addValue($attribute, $data);

        return response()->json($value, 201);
    }

    public function updateValue(Request $request, Attribute $attribute, AttributeValue $value): JsonResponse
    {
        abort_if($value->attribute_id !== $attribute->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'value_key' => ['sometimes', 'string', 'max:100', 'unique:attribute_values,value_key,'.$value->id.',id,attribute_id,'.$attribute->id],
            'display_value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $updated = $this->attributeService->updateValue($value, $data);

        return response()->json($updated);
    }

    public function destroyValue(Attribute $attribute, AttributeValue $value): JsonResponse
    {
        abort_if($value->attribute_id !== $attribute->id, 404);

        $this->attributeService->deleteValue($value);

        return response()->json(null, 204);
    }
}
