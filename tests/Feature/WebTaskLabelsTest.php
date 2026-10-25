<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTaskLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_labels_json_returns_all_labels(): void
    {
        $user = $this->actingUser();
        $bug = Label::create(['name' => 'Bug', 'color' => '#ef4444']);
        Label::create(['name' => 'Fitur', 'color' => '#22c55e']);

        $response = $this->actingAs($user)->getJson('/labels.json');

        $response->assertOk()
            ->assertJsonFragment(['id' => $bug->id, 'name' => 'Bug', 'color' => '#ef4444'])
            ->assertJsonFragment(['name' => 'Fitur']);
    }

    public function test_task_create_form_shows_label_chips_and_persists_task_labels(): void
    {
        $user = $this->actingUser();
        $labelA = Label::create(['name' => 'Prioritas', 'color' => '#f59e0b']);
        $labelB = Label::create(['name' => 'Backend', 'color' => '#3b82f6']);

        $this->actingAs($user)->get('/tasks/create')
            ->assertOk()
            ->assertSee('taskLabelPicker', false)
            ->assertSee('/labels.json', false);

        $response = $this->actingAs($user)->post('/tasks', [
            'title' => 'Task berlabel',
            'label_ids' => [$labelA->id, $labelB->id],
        ]);

        $task = Task::query()->where('title', 'Task berlabel')->first();
        $this->assertNotNull($task);
        $response->assertRedirect(route('tasks.show', $task));

        $this->assertEqualsCanonicalizing(
            [$labelA->id, $labelB->id],
            $task->labels()->pluck('labels.id')->all()
        );

        $this->actingAs($user)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Prioritas')
            ->assertSee('Backend');
    }
}
