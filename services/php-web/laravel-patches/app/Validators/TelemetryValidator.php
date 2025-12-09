<?php

namespace App\Validators;

use Illuminate\Http\Request;

class TelemetryValidator
{
    public array $validated = [];
    public array $errors = [];

    public function validate(Request $r): bool
    {
        $limit = $r->query('limit', 100);

        if (!is_numeric($limit) || $limit < 1 || $limit > 1000) {
            $this->errors['limit'] = 'Limit must be between 1 and 1000';
        }

        if (empty($this->errors)) {
            $this->validated = ['limit' => (int) $limit];
            return true;
        }
        return false;
    }
}
