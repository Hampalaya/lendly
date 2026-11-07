<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingImage extends Model
{
    protected $fillable = [
        'listing_id',
        'path',
        'sort_order',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(): string
    {
        if ($this->path === 'hosted-demo-placeholder') {
            return asset('images/hosted-demo-placeholder.svg');
        }

        return asset('storage/'.$this->path);
    }
}
