<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Validators\OsdrValidator;
use App\Services\IssService;

class OsdrController extends Controller
{
    public function index(Request $request, IssService $issService)
    {
        $validator = new OsdrValidator();
        if (!$validator->validate($request)) {
            return response()->view('osdr', ['items' => [], 'src' => '', 'errors' => $validator->errors]);
        }

        $limit = $validator->validated['limit'];
        $base = getenv('RUST_BASE') ?: 'http://rust_iss:3000';
        $data = $issService->getOsdrList($limit);
        $items = $this->flattenOsdr($data['items'] ?? []);

        return view('osdr', [
            'items' => $items,
            'src' => $base . '/osdr/list?limit=' . $limit,
        ]);
    }

    private function flattenOsdr(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            $raw = $row['raw'] ?? [];
            if (is_array($raw) && $this->looksOsdrDict($raw)) {
                foreach ($raw as $k => $v) {
                    if (!is_array($v)) continue;
                    $rest = $v['REST_URL'] ?? $v['rest_url'] ?? $v['rest'] ?? null;
                    $title = $v['title'] ?? $v['name'] ?? ($rest ? basename(rtrim($rest, '/')) : null);
                    $out[] = [
                        'id' => $row['id'],
                        'dataset_id' => $k,
                        'title' => $title,
                        'status' => $row['status'] ?? null,
                        'updated_at' => $row['updated_at'] ?? null,
                        'inserted_at' => $row['inserted_at'] ?? null,
                        'rest_url' => $rest,
                        'raw' => $v,
                    ];
                }
            } else {
                $row['rest_url'] = is_array($raw) ? ($raw['REST_URL'] ?? $raw['rest_url'] ?? null) : null;
                $out[] = $row;
            }
        }
        return $out;
    }

    private function looksOsdrDict(array $raw): bool
    {
        foreach ($raw as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'OSD-')) return true;
            if (is_array($v) && (isset($v['REST_URL']) || isset($v['rest_url']))) return true;
        }
        return false;
    }
}
