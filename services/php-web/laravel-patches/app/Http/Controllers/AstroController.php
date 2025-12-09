<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Validators\AstroValidator;

class AstroController extends Controller
{
    public function events(Request $r)
    {
        $validator = new AstroValidator();
        if (!$validator->validate($r)) {
            return response()->json(['errors' => $validator->errors], 422);
        }

        $v = $validator->validated;
        $from = now('UTC')->toDateString();
        $to = now('UTC')->addDays($v['days'])->toDateString();

        $appId = env('ASTRO_APP_ID', '');
        $secret = env('ASTRO_APP_SECRET', '');
        if ($appId === '' || $secret === '') {
            return response()->json(['error' => 'Missing ASTRO_APP_ID/ASTRO_APP_SECRET'], 500);
        }

        $auth = base64_encode($appId . ':' . $secret);
        $query = http_build_query([
            'latitude' => $v['lat'],
            'longitude' => $v['lon'],
            'from_date' => $from,
            'to_date' => $to,
            'elevation' => $v['elevation'],
            'time' => $v['time']
        ]);

        $allEvents = [];
        foreach (['sun', 'moon'] as $body) {
            $url = "https://api.astronomyapi.com/api/v2/bodies/events/{$body}?{$query}";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $auth, 'Content-Type: application/json'],
                CURLOPT_TIMEOUT => 25,
            ]);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
            curl_close($ch);

            if ($raw !== false && $code < 400) {
                $data = json_decode($raw, true);
                $events = $data['data']['table']['rows'][0]['cells'] ?? [];
                foreach ($events as $event) {
                    $event['body'] = $body;
                    $event['name'] = ucfirst($body);
                    $event['date'] = $event['eventHighlights']['peak']['date'] ?? null;
                    $extra = $event['extraInfo'] ?? [];
                    $event['note'] = implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($extra), $extra));
                    $allEvents[] = $event;
                }
            }
        }

        return response()->json(['events' => $allEvents, 'count' => count($allEvents)]);
    }
}
