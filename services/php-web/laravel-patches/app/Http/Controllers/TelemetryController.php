<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function index()
    {
        return view('telemetry');
    }
}
