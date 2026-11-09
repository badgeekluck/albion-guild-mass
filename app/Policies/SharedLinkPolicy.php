<?php

namespace App\Policies;

use App\Models\SharedLink;
use App\Models\User;

/**
 * Single source of truth for party permissions.
 *
 * - manage:  finish/delete the event, change extra slots, ping roster, attendance
 *            => admins and the party's creator
 * - arrange: drag & drop any player
 *            => staff (admin / content-creator) and the party's creator
 */
class SharedLinkPolicy
{
    public function manage(User $user, SharedLink $link): bool
    {
        return $user->isAdmin() || $user->id === $link->creator_id;
    }

    public function arrange(User $user, SharedLink $link): bool
    {
        return $user->isStaff() || $user->id === $link->creator_id;
    }

    /** Saving a roster as a template creates a staff resource. */
    public function saveAsTemplate(User $user, SharedLink $link): bool
    {
        return $user->isStaff() && $this->manage($user, $link);
    }
}
