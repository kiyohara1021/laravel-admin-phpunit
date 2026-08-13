<?php

namespace App\Models;

use App\Enums\NoticeStatus;
use App\Policies\NoticePolicy;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $published_at
 * @property User|null $user
 */
#[Fillable(['title', 'body', 'published_at'])]
#[UsePolicy(NoticePolicy::class)]
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

    /**
     * タイトルと本文の部分一致で絞り込む。前後の空白は無視し、空なら絞り込まない
     *
     * @param  Builder<Notice>  $query
     * @return Builder<Notice>
     */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return $query;
        }

        $pattern = '%'.self::escapeLike($keyword).'%';

        // OR を入れ子の where で包む。published() など前の条件の外に漏れないようにするため
        return $query->where(function (Builder $query) use ($pattern): void {
            $query
                ->whereRaw("title like ? escape '!'", [$pattern])
                ->orWhereRaw("body like ? escape '!'", [$pattern]);
        });
    }

    /**
     * 状態で絞り込む。null なら絞り込まない（名前は status() と紛らわしくないよう ofStatus）
     *
     * @param  Builder<Notice>  $query
     * @return Builder<Notice>
     */
    public function scopeOfStatus(Builder $query, ?NoticeStatus $status): Builder
    {
        return match ($status) {
            NoticeStatus::Draft => $query->draft(),
            NoticeStatus::Published => $query->published(),
            NoticeStatus::Scheduled => $query->scheduled(),
            null => $query,
        };
    }

    /**
     * LIKE のワイルドカードをリテラルに戻す。
     * エスケープ記号自身を先に置換しないと、あとから足すエスケープ記号まで二重に潰れる。
     *
     * 記号にバックスラッシュを使わないのは、SQL 中に `escape '\'` という並びができると
     * toRawSql() / dumpRawSql() の文字列リテラル判定が壊れ、以降の値が ? のまま残るため
     */
    private static function escapeLike(string $keyword): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
    }
}
