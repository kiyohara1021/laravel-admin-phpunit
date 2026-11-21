<?php

namespace App\Http\Resources;

use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公開 API 向けのお知らせ。作成者や状態など公開画面に出していない項目は含めない。
 *
 * @property Notice $resource
 */
class NoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'published_at' => $this->resource->published_at?->toIso8601String(),
        ];
    }
}
