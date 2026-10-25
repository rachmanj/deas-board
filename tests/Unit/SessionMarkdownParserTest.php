<?php

namespace Tests\Unit;

use App\Support\SessionMarkdownParser;
use Tests\TestCase;

class SessionMarkdownParserTest extends TestCase
{
    public function test_parses_markdown_table_rows(): void
    {
        $markdown = file_get_contents(base_path('tests/fixtures/sessions-sample.md'));
        $parser = new SessionMarkdownParser;

        $rows = $parser->parse($markdown);

        $this->assertCount(2, $rows);
        $this->assertSame('35147', $rows[0]['topic_id']);
        $this->assertSame('Arka Click', $rows[0]['label']);
        $this->assertStringContainsString('penulis', $rows[0]['notes']);
        $this->assertStringContainsString('Catatan panjang untuk sesi pertama.', $rows[0]['notes']);
        $this->assertSame('42099', $rows[1]['topic_id']);
        $this->assertSame('Beta Node', $rows[1]['label']);
        $this->assertStringContainsString('verifikator', $rows[1]['notes']);
    }
}
