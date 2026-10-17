<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'content', 'is_published', 'show_in_footer', 'sort_order', 'seo_description'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'show_in_footer' => 'boolean'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function url(): string
    {
        return route('pages.show', $this->slug);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
