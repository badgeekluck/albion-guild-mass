<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SharedLink extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'expires_at' => 'datetime',
        'template_snapshot' => 'array',
    ];

    protected $fillable = [
        'creator_id', 'slug', 'destination_url', 'expires_at', 'title', 'template_snapshot','title','type',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->slug = Str::random(6);
            $model->expires_at = now()->addHours(3);
        });
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(LinkAttendee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
