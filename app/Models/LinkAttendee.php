<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkAttendee extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function link()
    {
        return $this->belongsTo(SharedLink::class, 'link_id');
    }
    // ----------------------------------
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
