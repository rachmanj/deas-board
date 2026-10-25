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

    public function test_ignores_bold_numbers_outside_section_three_table(): void
    {
        $markdown = file_get_contents(base_path('tests/fixtures/sessions-sample.md'));
        $parser = new SessionMarkdownParser;

        $rows = $parser->parse($markdown);

        $topicIds = array_column($rows, 'topic_id');
        $this->assertNotContains('111', $topicIds);
        $this->assertNotContains('140', $topicIds);
        $this->assertCount(3, $rows);
    }

    public function test_without_section_three_only_parses_pipe_prefixed_lines(): void
    {
        $markdown = <<<'MD'
Teks **999** tanpa pipa.

| Topik | Peran | Langkah |
| --- | --- | --- |
| **42** App Tanpa Bagian | penulis | Catatan. |
MD;

        $parser = new SessionMarkdownParser;
        $rows = $parser->parse($markdown);

        $this->assertCount(1, $rows);
        $this->assertSame('42', $rows[0]['topic_id']);
        $this->assertSame('App Tanpa Bagian', $rows[0]['label']);
    }
}
