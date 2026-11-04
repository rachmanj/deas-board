<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\TaskComment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class TextNormalizerTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_clean_repairs_common_mojibake_without_touching_valid_text(): void
    {
        $enDashBroken = "\u{00E2}\u{0080}\u{0093}";
        $arrowBroken = "\u{00E2}\u{0086}\u{0092}";

        $this->assertSame('Juli–September', TextNormalizer::clean('Juli'.$enDashBroken.'September'));
        $this->assertSame('Langkah → selesai', TextNormalizer::clean('Langkah '.$arrowBroken.' selesai'));
        $this->assertSame('Teks biasa tanpa masalah', TextNormalizer::clean('Teks biasa tanpa masalah'));
    }

    public function test_api_task_store_normalizes_title_description_and_external_ref(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '910', 'is_active' => true]);
        $enDashBroken = "\u{00E2}\u{0080}\u{0093}";
        $arrowBroken = "\u{00E2}\u{0086}\u{0092}";

        $response = $this->asSession($session)->postJson('/api/tasks', [
            'title' => 'Juli'.$enDashBroken.'September',
            'description_md' => 'Alur '.$arrowBroken.' done',
            'external_ref' => 'ref'.$enDashBroken.'1',
        ]);

        $response->assertCreated();

        $task = Task::query()->latest('id')->first();
        $this->assertNotNull($task);
        $this->assertSame('Juli–September', $task->title);
        $this->assertSame('Alur → done', $task->description_md);
        $this->assertSame('ref–1', $task->external_ref);
    }

    public function test_api_comment_and_update_endpoints_normalize_body_and_evidence(): void
    {
        $session = Session::create(['label' => 'S', 'topic_id' => '911', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-91',
            'title' => 'Normalize paths',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);
        $arrowBroken = "\u{00E2}\u{0086}\u{0092}";

        $this->asSession($session)->postJson("/api/tasks/{$task->id}/comments", [
            'body_md' => 'Catatan '.$arrowBroken,
        ])->assertCreated();

        $comment = TaskComment::query()->where('task_id', $task->id)->first();
        $this->assertSame('Catatan →', $comment->body_md);

        $this->asSession($session)->postJson("/api/tasks/{$task->id}/updates", [
            'status' => 'review',
            'note' => 'Note '.$arrowBroken,
            'evidence' => 'PR '.$arrowBroken,
        ])->assertCreated();

        $update = $task->updates()->latest('id')->first();
        $this->assertSame('Note →', $update->note);
        $this->assertSame('PR →', $update->evidence);
    }
}
