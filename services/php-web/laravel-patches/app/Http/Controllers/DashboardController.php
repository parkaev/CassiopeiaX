<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\JwstHelper;
use App\Services\CmsBlockService;
use App\Services\IssService;
use App\Validators\JwstFeedValidator;

class DashboardController extends Controller
{
    public function index(CmsBlockService $cmsService, IssService $issService)
    {
        $iss = $issService->getLast();

        return view('dashboard', [
            'iss' => $iss,
            'trend' => [],
            'jw_gallery' => [],
            'jw_observation_raw' => [],
            'jw_observation_summary' => [],
            'jw_observation_images' => [],
            'jw_observation_files' => [],
            'metrics' => [
                'iss_speed' => $iss['payload']['velocity'] ?? null,
                'iss_alt' => $iss['payload']['altitude'] ?? null,
                'neo_total' => 0,
            ],
            'dashboard_welcome' => $cmsService->getBlockContent('dashboard_welcome'),
            'dashboard_unsafe' => $cmsService->getBlockContent('dashboard_unsafe'),
            'dashboard_not_found' => $cmsService->getBlockContent('dashboard_not_found'),
        ]);
    }

    public function jwstFeed(Request $r)
    {
        $validator = new JwstFeedValidator();
        if (!$validator->validate($r)) {
            return response()->json(['errors' => $validator->errors], 422);
        }

        $v = $validator->validated;
        $jw = new JwstHelper();

        $path = 'all/type/jpg';
        if ($v['source'] === 'suffix' && $v['suffix'] !== '') {
            $path = 'all/suffix/' . ltrim($v['suffix'], '/');
        }
        if ($v['source'] === 'program' && $v['program'] !== '') {
            $path = 'program/id/' . rawurlencode($v['program']);
        }

        $resp = $jw->get($path, ['page' => $v['page'], 'perPage' => $v['perPage']]);
        $list = $resp['body'] ?? ($resp['data'] ?? (is_array($resp) ? $resp : []));

        $items = [];
        foreach ($list as $it) {
            if (!is_array($it)) continue;

            $url = null;
            foreach ([$it['location'] ?? null, $it['thumbnail'] ?? null] as $u) {
                if (is_string($u) && preg_match('~\.(jpg|jpeg|png)(\?.*)?$~i', $u)) {
                    $url = $u;
                    break;
                }
            }
            if (!$url) $url = JwstHelper::pickImageUrl($it);
            if (!$url) continue;

            $instList = [];
            foreach (($it['details']['instruments'] ?? []) as $I) {
                if (is_array($I) && !empty($I['instrument'])) {
                    $instList[] = strtoupper($I['instrument']);
                }
            }
            if ($v['instrument'] && $instList && !in_array($v['instrument'], $instList, true)) continue;

            $items[] = [
                'url' => $url,
                'obs' => (string) ($it['observation_id'] ?? $it['observationId'] ?? ''),
                'program' => (string) ($it['program'] ?? ''),
                'suffix' => (string) ($it['details']['suffix'] ?? $it['suffix'] ?? ''),
                'inst' => $instList,
                'caption' => trim(
                    (($it['observation_id'] ?? '') ?: ($it['id'] ?? '')) .
                    ' · P' . ($it['program'] ?? '-') .
                    (($it['details']['suffix'] ?? '') ? ' · ' . $it['details']['suffix'] : '') .
                    ($instList ? ' · ' . implode('/', $instList) : '')
                ),
                'link' => ($it['location'] ?? null) ?: $url,
            ];
            if (count($items) >= $v['perPage']) break;
        }

        return response()->json(['source' => $path, 'count' => count($items), 'items' => $items]);
    }
}
