<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class IssService
{
    private function base(): string
    {
        return getenv('RUST_BASE') ?: 'http://rust_iss:3000';
    }

    public function getLast(): array
    {
        return Cache::remember('iss_last', 30, function () {
            $raw = @file_get_contents($this->base() . '/last');
            return $raw ? (json_decode($raw, true) ?: []) : [];
        });
    }

    public function getTrend(?string $queryString = null): array
    {
        $key = 'iss_trend' . ($queryString ? ':' . md5($queryString) : '');
        return Cache::remember($key, 60, function () use ($queryString) {
            $url = $this->base() . '/iss/trend' . ($queryString ? '?' . $queryString : '');
            $raw = @file_get_contents($url);
            return $raw ? (json_decode($raw, true) ?: []) : [];
        });
    }

    public function getOsdrList(int $limit = 20): array
    {
        return Cache::remember("osdr_list:{$limit}", 3600, function () use ($limit) {
            $raw = @file_get_contents($this->base() . '/osdr/list?limit=' . $limit);
            return $raw ? (json_decode($raw, true) ?: ['items' => []]) : ['items' => []];
        });
    }
}
