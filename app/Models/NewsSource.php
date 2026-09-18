<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'niches',
        'connector',
        'endpoint',
        'config',
        'license_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'niches' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_news_source')
            ->withPivot('last_fetched_at')
            ->withTimestamps();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
