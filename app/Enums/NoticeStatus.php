<?php

namespace App\Enums;

enum NoticeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Published => '公開',
            self::Scheduled => '予約公開',
        };
    }
}
