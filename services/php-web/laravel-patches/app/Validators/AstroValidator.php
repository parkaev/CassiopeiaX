<?php

namespace App\Validators;

use Illuminate\Http\Request;

class AstroValidator
{
    public array $validated = [];
    public array $errors = [];

    public function validate(Request $r): bool
    {
        $lat = $r->query('lat', 55.7558);
        $lon = $r->query('lon', 37.6176);
        $days = $r->query('days', 7);
        $elevation = $r->query('elevation', 0);
        $time = $r->query('time', '00:00');

        if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
            $this->errors['lat'] = 'Latitude must be between -90 and 90';
        }
        if (!is_numeric($lon) || $lon < -180 || $lon > 180) {
            $this->errors['lon'] = 'Longitude must be between -180 and 180';
        }
        if (!is_numeric($days) || $days < 1 || $days > 30) {
            $this->errors['days'] = 'Days must be between 1 and 30';
        }
        if (!is_numeric($elevation) || $elevation < -500 || $elevation > 10000) {
            $this->errors['elevation'] = 'Elevation must be between -500 and 10000';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $this->errors['time'] = 'Time must be in HH:MM format';
        }

        if (empty($this->errors)) {
            $this->validated = [
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'days' => (int) $days,
                'elevation' => (int) $elevation,
                'time' => $time,
            ];
            return true;
        }
        return false;
    }
}
