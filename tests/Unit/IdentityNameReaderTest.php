<?php

namespace Tests\Unit;

use App\Services\IdentityNameReader;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IdentityNameReaderTest extends TestCase
{
    #[DataProvider('cards')]
    public function test_it_reads_a_name_and_ignores_other_card_fields(string $text, ?string $expected): void
    {
        Process::fake(fn () => Process::result(output: $text));

        $this->assertSame($expected, app(IdentityNameReader::class)->read('sample-id.png', 'Maria Elena Santos'));
    }

    public static function cards(): array
    {
        return [
            'labelled name differs from account' => ["School Student ID\nFull Name: Ana Dela Cruz\nStudent number: 12345", 'Ana Dela Cruz'],
            'name on the next line' => ["Employee ID\nName\nAna Dela Cruz\n12345", 'Ana Dela Cruz'],
            'separate name fields' => ["Apelyido/Last Name\nSANTOS\nMga Pangalan/Given Names\nMARIA ELENA\nMiddle Name: REYES", 'MARIA ELENA REYES SANTOS'],
            'unlabelled name matching account' => ["STUDENT CARD\nMARIA ELENA SANTOS\n12345", 'MARIA ELENA SANTOS'],
            'no readable name' => ["STUDENT CARD\nName: 12345\n2026-11-01", null],
            'missing name followed by another field' => ["STUDENT CARD\nName\nDate of Birth\n2026-11-01", null],
        ];
    }

    public function test_an_ocr_engine_failure_returns_no_name(): void
    {
        Process::fake(fn () => Process::result(exitCode: 1));

        $this->assertNull(app(IdentityNameReader::class)->read('sample-id.png', 'Maria Elena Santos'));
    }
}
