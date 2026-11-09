<?php

namespace App\Services\Discord;

enum MembershipStatus
{
    case Allowed;
    case NotMember;
    case MissingRole;
    /** Discord could not be reached / answered with an unexpected error. */
    case Unavailable;
}
