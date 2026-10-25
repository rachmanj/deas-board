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

        $this->assertCount(3, $rows);
        $this->assertSame('2', $rows[0]['topic_id']);
        $this->assertSame('POS-ATK', $rows[0]['label']);
        $this->assertStringContainsString('penulis', $rows[0]['notes']);
        $this->assertStringContainsString('Catatan panjang untuk sesi pertama.', $rows[0]['notes']);
        $this->assertSame('29724', $rows[1]['topic_id']);
        $this->assertSame('AccountingOne', $rows[1]['label']);
        $this->assertStringContainsString('verifikator', $rows[1]['notes']);
        $this->assertSame('12488', $rows[2]['topic_id']);
        $this->assertSame('ARK-GS', $rows[2]['label']);
        $this->assertStringContainsString('penulis', $rows[2]['notes']);
    }
}
