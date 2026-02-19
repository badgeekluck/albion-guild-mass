<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedBuild extends Model
{
    protected $guarded = [];

    public function weapon()
    {
        return $this->belongsTo(GameRole::class, 'weapon_id');
    }
    public function offhand()
    {
        return $this->belongsTo(GameRole::class, 'offhand_id');
    }
    public function head()
    {
        return $this->belongsTo(GameRole::class, 'head_id');
    }
    public function armor()
    {
        return $this->belongsTo(GameRole::class, 'armor_id');
    }
    public function shoe()
    {
        return $this->belongsTo(GameRole::class, 'shoe_id');
    }
    public function cape()
    {
        return $this->belongsTo(GameRole::class, 'cape_id');
    }

    public function food()
    {
        return $this->belongsTo(GameRole::class, 'food_id');
    }
    public function potion()
    {
        return $this->belongsTo(GameRole::class, 'potion_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
