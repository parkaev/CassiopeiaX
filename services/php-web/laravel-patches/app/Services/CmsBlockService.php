<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CmsBlockService
{
    public function getBlockContent(string $slug): ?string
    {
        return Cache::remember("cms_block_{$slug}", 3600, function () use ($slug) {
            $block = DB::table('cms_blocks')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first(['content']);
            
            return $block?->content ? $this->sanitize($block->content) : null;
        });
    }

    private function sanitize(string $content): string
    {
        return $this->sanitizeHtml($content);
    }

    /**
     * Санитизация HTML: удаление опасных тегов и атрибутов
     */
    private function sanitizeHtml(?string $html): string
    {
        if ($html === null) {
            return '';
        }
        // Удаляем script, style, iframe, object, embed теги
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)[^>]*\/?>/is', '', $html);
        // Удаляем on* атрибуты (onclick, onerror и т.д.)
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
        // Удаляем javascript: в href/src
        $html = preg_replace('/\b(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $html);
        return $html;
    }
}
