<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class IdentityNameReader
{
    public function read(string $path, string $accountName): ?string
    {
        try {
            $result = Process::timeout(6)->env(['OMP_THREAD_LIMIT' => '1'])->run([
                config('services.identity_ocr.binary'), $path, 'stdout', '-l', 'eng', '--psm', '11',
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $result->output())),
            fn (string $line) => $line !== ''));
        $fields = [];
        $labels = '/^
            (?:
                (?<full>full\h+name|name|buong\h+pangalan)
                | (?<first>(?:mga\h+pangalan\h*\/\h*)?(?:first|given)\h+names?)
                | (?<middle>(?:gitnang\h+pangalan\h*\/\h*)?middle\h+names?)
                | (?<last>(?:apelyido\h*\/\h*)?(?:(?:last|family)\h+names?|surname))
                | (?<suffix>suffix)
            )
            (?:\h*[:=-]\h*|\h{2,}|$)(?<value>.*)
        $/iux';

        foreach ($lines as $index => $line) {
            if (! preg_match($labels, $line, $match, PREG_UNMATCHED_AS_NULL)) {
                continue;
            }
            $name = trim($match['value']) ?: ($lines[$index + 1] ?? '');
            if (! $this->looksLikeName($name) || preg_match($labels, $name)) {
                continue;
            }
            if ($match['full'] !== null) {
                return $name;
            }
            foreach (['first', 'middle', 'last', 'suffix'] as $field) {
                if ($match[$field] !== null) {
                    $fields[$field] = $name;
                }
            }
        }

        if (isset($fields['first'], $fields['last'])) {
            $name = implode(' ', array_filter([$fields['first'], $fields['middle'] ?? null, $fields['last'], $fields['suffix'] ?? null]));
            if ($this->looksLikeName($name)) {
                return $name;
            }
        }

        $expected = Str::squish(Str::lower($accountName));
        foreach ($lines as $line) {
            if ($this->looksLikeName($line) && Str::squish(Str::lower($line)) === $expected) {
                return $line;
            }
        }

        return null;
    }

    private function looksLikeName(string $name): bool
    {
        return mb_strlen($name) <= 120
            && preg_match("/^\p{L}[\p{L}\p{M} .'’-]*$/u", $name) === 1
            && ! in_array(Str::lower(Str::squish($name)), [
                'date of birth', 'birth date', 'place of birth', 'address', 'sex', 'gender',
                'nationality', 'citizenship', 'blood type', 'student number', 'employee number', 'id number',
                'date of issue', 'expiry date',
            ], true);
    }
}
