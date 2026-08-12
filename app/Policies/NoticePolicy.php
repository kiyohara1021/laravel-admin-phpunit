<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

/**
 * お知らせに対して何をしてよいかを決める。
 * 管理者だけが操作でき、作成者かどうかは見ない（他人のお知らせでも管理者なら編集できる）。
 */
class NoticePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * $notice は今の規則では見ないが、記録ごとの規則を足せるように受け取っておく。
     */
    public function update(User $user, Notice $notice): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Notice $notice): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, Notice $notice): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, Notice $notice): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->isAdmin();
    }
}
