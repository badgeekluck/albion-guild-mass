<?php

use App\Models\SharedLink;
use Illuminate\Support\Facades\Broadcast;

// Presence channel for a party screen: returns the viewer info shown in "Live Viewers".
Broadcast::channel('party.{slug}', function ($user, string $slug) {
    if (! SharedLink::where('slug', $slug)->exists()) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
