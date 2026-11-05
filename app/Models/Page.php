<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A static content page — "من نحن", "سياسة الخصوصية", "الشروط والأحكام" —
 * written in the admin panel rather than hard-coded in a Blade file, so the
 * shop's own staff can keep the legal copy current without a deploy.
 *
 * `content` holds the HTML Filament's rich editor produces. It is rendered
 * unescaped, which is safe only because authoring is behind the admin guard;
 * it must never be fed from customer input.
 */
class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'menu_title',
        'meta_description',
        'content',
        'is_active',
        'show_in_footer',
        'sort_order',
    ];

    protected $attributes = [
        'is_active' => true,
        'show_in_footer' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The footer's list, in the order the admin arranged it. */
    public function scopeInFooter(Builder $query): Builder
    {
        return $query->active()
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /** Short label where space is tight; the full title otherwise. */
    public function menuLabel(): string
    {
        return $this->menu_title ?: $this->title;
    }
}
