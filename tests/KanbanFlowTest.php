<?php

namespace Thevps\Kanban\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Thevps\Kanban\Events\CardAssigned;
use Thevps\Kanban\Models\KanbanBoard;
use Thevps\Kanban\Models\KanbanCard;
use Thevps\Kanban\Models\KanbanCardChecklist;

class KanbanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_card_and_checklist_lifecycle(): void
    {
        $user = TestUser::create(['name' => 'Owner']);
        $this->actingAs($user);

        $this->post(route('kanban.store'), ['title' => 'Board'])->assertRedirect();
        $board = KanbanBoard::firstOrFail();
        $this->assertSame(4, $board->columns()->count());

        $this->put(route('kanban.update', $board), ['title' => 'Board', 'code' => 'abc'])->assertRedirect();
        $this->assertSame('ABC', $board->refresh()->code);

        $column = $board->columns()->orderBy('order_column')->first();
        $done = $board->columns()->where('is_done', true)->first();

        $this->post(route('kanban.cards.store', $board), ['column_id' => $column->id, 'title' => 'Task'])->assertRedirect();
        $card = KanbanCard::firstOrFail();
        $this->assertSame(1, $card->number);

        $this->post(route('kanban.cards.checklists.store', [$board, $card]), ['title' => 'Item'])->assertRedirect();
        $item = KanbanCardChecklist::firstOrFail();

        $this->put(route('kanban.cards.checklists.update', [$board, $card, $item]), ['title' => 'Item edited'])->assertRedirect();
        $this->assertSame('Item edited', $item->refresh()->title);

        $this->put(route('kanban.cards.checklists.update', [$board, $card, $item]), ['is_completed' => true])->assertRedirect();
        $this->assertTrue($item->refresh()->is_completed);

        $this->post(route('kanban.cards.move', [$board, $card]), ['column_id' => $done->id, 'order' => [$card->id]])->assertRedirect();
        $this->assertSame($done->id, $card->refresh()->column_id);

        $this->post(route('kanban.cards.archive', [$board, $card]))->assertRedirect();
        $this->assertNotNull($card->refresh()->archived_at);
    }

    public function test_card_assigned_event_fires_for_other_user_only(): void
    {
        Event::fake([CardAssigned::class]);

        $owner = TestUser::create(['name' => 'Owner']);
        $other = TestUser::create(['name' => 'Other']);
        $this->actingAs($owner);

        $this->post(route('kanban.store'), ['title' => 'B']);
        $board = KanbanBoard::firstOrFail();
        $column = $board->columns()->first();

        $this->post(route('kanban.cards.store', $board), ['column_id' => $column->id, 'title' => 'T', 'assigned_to_id' => $other->id]);
        Event::assertDispatched(CardAssigned::class);

        $this->post(route('kanban.cards.store', $board), ['column_id' => $column->id, 'title' => 'Self', 'assigned_to_id' => $owner->id]);
        Event::assertDispatchedTimes(CardAssigned::class, 1);
    }
}
