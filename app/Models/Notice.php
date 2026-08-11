<?php

namespace App\Models;

use App\Enums\NoticeStatus;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'body', 'published_at'])]
class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 作成者。ユーザーが削除されると null になる
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function status(): NoticeStatus
    {
        if ($this->published_at === null) {
            return NoticeStatus::Draft;
        }

        return $this->published_at->isFuture()
            ? NoticeStatus::Scheduled
            : NoticeStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status() === NoticeStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status() === NoticeStatus::Published;
    }

    public function isScheduled(): bool
    {
        return $this->status() === NoticeStatus::Scheduled;
    }

    /**
     * @param  Builder<Notice>  $query
     * @return Builder<Notice>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->whereNull('published_at');
    }

    /**
     * @param  Builder<Notice>  $query
     * @return Builder<Notice>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Notice>  $query
     * @return Builder<Notice>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '>', now());
    }
}
