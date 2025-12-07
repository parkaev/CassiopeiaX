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
            
            return $block?->content;
        });
    }
}
