<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'description' => $this->description,
            'category' => $this->category,
            'images' => collect($this->images)
                ->map(fn($image) => Storage::disk('public')->url($image))
                ->values()
                ->all(),
            'created_at' => $this->created_at,
            'created_by' => $this->createdBy?->name,
            'created_by_id' => $this->created_by_id,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updatedBy?->name,
            'updated_by_id' => $this->updated_by_id,
        ];
    }
}
