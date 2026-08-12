<?php

namespace App\Support;

use App\Enums\NoticeStatus;
use Illuminate\Http\Request;

/**
 * 一覧の絞り込み条件。クエリ文字列の読み取りをここ 1 か所に集める。
 *
 * FormRequest にしないのは、GET の絞り込みが「弾くべき入力」ではなく画面の表示状態だから。
 * 不正な値はエラーにせず、すべて無視して絞り込まない扱いにする。
 */
final readonly class NoticeFilter
{
    public function __construct(
        public string $keyword = '',
        public ?NoticeStatus $status = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $keyword = $request->query('q');
        $status = $request->query('status');

        return new self(
            // 配列などが来ても 500 にせず、絞り込みなしとして扱う
            keyword: is_string($keyword) ? trim($keyword) : '',
            // 存在しない状態は無視する（画面の状態セレクトは「すべて」に戻る）
            status: is_string($status) ? NoticeStatus::tryFrom($status) : null,
        );
    }

    public function hasKeyword(): bool
    {
        return $this->keyword !== '';
    }

    public function isActive(): bool
    {
        return $this->hasKeyword() || $this->status !== null;
    }
}
