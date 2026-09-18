<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_IMAGE = 'pending_image';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'news_source_id',
        'prompt_template_id',
        'external_lead_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'source_reference_title',
        'source_reference_url',
        'featured_image_url',
        'featured_image_credit_name',
        'featured_image_credit_url',
        'status',
        'scheduled_for',
        'published_at',
        'generation_meta',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'generation_meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function newsSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class);
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', now());
    }
}
