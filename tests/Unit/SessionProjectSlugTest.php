<?php

namespace Tests\Unit;

use App\Support\SessionProjectSlug;
use PHPUnit\Framework\TestCase;

class SessionProjectSlugTest extends TestCase
{
    public function test_sanitizes_em_dash_and_slash_in_label(): void
    {
        $this->assertSame(
            'accountingone-lap-keu',
            SessionProjectSlug::fromLabel('AccountingOne — LAP-KEU')
        );
        $this->assertSame(
            'pratasaba-hotel',
            SessionProjectSlug::fromLabel('Pratasaba/Hotel')
        );
    }

    public function test_empty_label_falls_back_to_proyek(): void
    {
        $this->assertSame('proyek', SessionProjectSlug::fromLabel('   ---   '));
    }
}
