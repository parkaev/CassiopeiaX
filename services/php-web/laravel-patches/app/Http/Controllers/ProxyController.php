<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use App\Services\IssService;

class ProxyController extends Controller
{
    public function last(IssService $issService)
    {
        $data = $issService->getLast();
        return new Response(json_encode($data) ?: '{}', 200, ['Content-Type' => 'application/json']);
    }

    public function trend(IssService $issService)
    {
        $q = request()->getQueryString();
        $data = $issService->getTrend($q);
        return new Response(json_encode($data) ?: '{}', 200, ['Content-Type' => 'application/json']);
    }
}
