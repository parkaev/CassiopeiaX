<?php

namespace App\Validators;

use Illuminate\Http\Request;

class JwstFeedValidator
{
    public array $validated = [];
    public array $errors = [];

    private const VALID_SOURCES = ['jpg', 'suffix', 'program'];
    private const VALID_INSTRUMENTS = ['NIRCAM', 'MIRI', 'NIRISS', 'NIRSPEC', 'FGS', ''];

    public function validate(Request $r): bool
    {
        $source = $r->query('source', 'jpg');
        $suffix = trim((string) $r->query('suffix', ''));
        $program = trim((string) $r->query('program', ''));
        $instrument = strtoupper(trim((string) $r->query('instrument', '')));
        $page = $r->query('page', 1);
        $perPage = $r->query('perPage', 24);

        if (!in_array($source, self::VALID_SOURCES, true)) {
            $this->errors['source'] = 'Source must be: jpg, suffix, or program';
        }
        if (!in_array($instrument, self::VALID_INSTRUMENTS, true)) {
            $this->errors['instrument'] = 'Invalid instrument';
        }
        if (!is_numeric($page) || $page < 1) {
            $this->errors['page'] = 'Page must be >= 1';
        }
        if (!is_numeric($perPage) || $perPage < 1 || $perPage > 60) {
            $this->errors['perPage'] = 'perPage must be between 1 and 60';
        }

        if (empty($this->errors)) {
            $this->validated = [
                'source' => $source,
                'suffix' => $suffix,
                'program' => $program,
                'instrument' => $instrument,
                'page' => max(1, (int) $page),
                'perPage' => max(1, min(60, (int) $perPage)),
            ];
            return true;
        }
        return false;
    }
}
