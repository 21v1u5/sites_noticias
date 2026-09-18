<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'niche',
        'domain',
        'tagline',
        'locale',
        'timezone',
        'theme',
        'adsense_client_id',
        'adsense_slots',
        'publish_spacing_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'adsense_slots' => 'array',
            'publish_spacing_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function newsSources(): BelongsToMany
    {
        return $this->belongsToMany(NewsSource::class, 'site_news_source')
            ->withPivot('last_fetched_at')
            ->withTimestamps();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function adsenseSlot(string $position): ?string
    {
        return $this->adsense_slots[$position] ?? null;
    }
}
