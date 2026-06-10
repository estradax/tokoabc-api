<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'path' => is_array($this->resource) ? $this->resource['path'] : $this->resource->path,
            'url' => is_array($this->resource) ? $this->resource['url'] : $this->resource->url,
        ];
    }
}
