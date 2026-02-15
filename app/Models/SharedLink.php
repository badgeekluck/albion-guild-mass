<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SharedLink extends Model
{
    protected $casts = [
        'expires_at' => 'datetime',
        'template_snapshot' => 'array', // <--- BUNU EKLE (JSON'ı dizi yapar)
    ];

    protected $fillable = [
        'creator_id', 'slug', 'destination_url', 'expires_at', 'title', 'template_snapshot','title',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->slug = Str::random(6);
            $model->expires_at = now()->addHours(3);
        });
    }

    public function attendees()
    {
        return $this->hasMany(LinkAttendee::class);
    }
}
