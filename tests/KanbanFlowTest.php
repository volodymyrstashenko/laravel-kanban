<?php

namespace Thevps\Kanban\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Thevps\Kanban\Events\CardAssigned;
use Thevps\Kanban\Models\KanbanBoard;
use Thevps\Kanban\Models\KanbanCard;
use Thevps\Kanban\Models\KanbanCardChecklist;
use Thevps\Kanban\Models\KanbanCardLink;

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

    public function test_card_link_stores_fetched_page_title(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>  Example &amp; Domain  </title></head><body>x</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs(TestUser::create(['name' => 'Owner']));
        $this->post(route('kanban.store'), ['title' => 'B']);
        $board = KanbanBoard::firstOrFail();
        $this->post(route('kanban.cards.store', $board), ['column_id' => $board->columns()->first()->id, 'title' => 'T']);
        $card = KanbanCard::firstOrFail();

        $this->post(route('kanban.cards.links.store', [$board, $card]), ['url' => 'https://example.com/page'])->assertRedirect();

        $link = KanbanCardLink::firstOrFail();
        $this->assertSame('https://example.com/page', $link->url);
        $this->assertSame('Example & Domain', $link->title);
        $this->assertDatabaseHas('kanban_activities', ['card_id' => $card->id, 'type' => 'link_added']);

        $this->delete(route('kanban.cards.links.destroy', [$board, $card, $link]))->assertRedirect();
        $this->assertDatabaseCount('kanban_card_links', 0);
        $this->assertDatabaseHas('kanban_activities', ['card_id' => $card->id, 'type' => 'link_removed']);
    }

    public function test_card_link_to_private_host_is_stored_without_title_and_without_outbound_request(): void
    {
        Http::fake();

        $this->actingAs(TestUser::create(['name' => 'Owner']));
        $this->post(route('kanban.store'), ['title' => 'B']);
        $board = KanbanBoard::firstOrFail();
        $this->post(route('kanban.cards.store', $board), ['column_id' => $board->columns()->first()->id, 'title' => 'T']);
        $card = KanbanCard::firstOrFail();

        $this->post(route('kanban.cards.links.store', [$board, $card]), ['url' => 'http://127.0.0.1/admin'])->assertRedirect();

        $this->assertNull(KanbanCardLink::firstOrFail()->title);
        Http::assertNothingSent();
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
