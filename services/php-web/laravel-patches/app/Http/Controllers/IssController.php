<?php

namespace App\Http\Controllers;

use App\Services\IssService;

class IssController extends Controller
{
    public function index(IssService $issService)
    {
        $base = getenv('RUST_BASE') ?: 'http://rust_iss:3000';
        $last = $issService->getLast();
        $trend = $issService->getTrend();

        return view('iss', ['last' => $last, 'trend' => $trend, 'base' => $base]);
    }
}
