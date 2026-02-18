<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('party.{slug}', function ($user, $slug) {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
