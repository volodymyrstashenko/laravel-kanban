<?php

use Illuminate\Support\Facades\Route;
use Thevps\Kanban\Http\Controllers\KanbanController;

/*
 | Loaded by KanbanServiceProvider inside a group that applies:
 |   prefix     = config('kanban.route_prefix')      (default "kanban")
 |   middleware = config('kanban.route_middleware')   (default ['web', 'auth'])
 |   name       = "kanban."
 |
 | Access to a specific board is controlled by membership inside the controller
 | (authorizeBoardAction / authorizeCardAction), not by a global permission — mirror the
 | host app's own auth stack via route_middleware only.
*/

Route::get('/', [KanbanController::class, 'index'])->name('index');
Route::post('/', [KanbanController::class, 'storeBoard'])->name('store');
Route::post('/reorder', [KanbanController::class, 'reorderBoards'])->name('reorder');

Route::prefix('{board}')->group(function () {
    Route::get('/', [KanbanController::class, 'show'])->name('show');
    Route::put('/', [KanbanController::class, 'update'])->name('update');
    Route::delete('/', [KanbanController::class, 'destroy'])->name('destroy');

    Route::post('/members', [KanbanController::class, 'addMember'])->name('members.store');
    Route::delete('/members/{user}', [KanbanController::class, 'removeMember'])->name('members.destroy');
    Route::put('/members/{user}', [KanbanController::class, 'updateMemberRole'])->name('members.update');

    Route::post('/columns', [KanbanController::class, 'storeColumn'])->name('columns.store');
    Route::put('/columns/{column}', [KanbanController::class, 'updateColumn'])->name('columns.update');
    Route::delete('/columns/{column}', [KanbanController::class, 'destroyColumn'])->name('columns.destroy');
    Route::post('/columns/reorder', [KanbanController::class, 'reorderColumns'])->name('columns.reorder');

    Route::post('/cards', [KanbanController::class, 'storeCard'])->name('cards.store');
    Route::put('/cards/{card}', [KanbanController::class, 'updateCard'])->name('cards.update');
    Route::post('/cards/{card}/move', [KanbanController::class, 'moveCard'])->name('cards.move');
    Route::post('/columns/{column}/reorder-cards', [KanbanController::class, 'reorderCards'])->name('columns.reorder-cards');
    Route::delete('/cards/{card}', [KanbanController::class, 'destroyCard'])->name('cards.destroy');

    Route::post('/cards/{card}/archive', [KanbanController::class, 'archiveCard'])->name('cards.archive');
    Route::post('/cards/{card}/restore', [KanbanController::class, 'restoreCard'])->name('cards.restore');
    Route::post('/cards/{card}/assign-me', [KanbanController::class, 'assignToMe'])->name('cards.assign-me');

    Route::get('/cards/{card}/linkable-cards', [KanbanController::class, 'searchLinkableCards'])->name('cards.linkable-cards');
    Route::post('/cards/{card}/link-subtask', [KanbanController::class, 'linkSubtask'])->name('cards.link-subtask');
    Route::delete('/cards/{card}/subtasks/{subtask}', [KanbanController::class, 'unlinkSubtask'])->name('cards.subtasks.destroy');

    Route::post('/cards/{card}/checklists', [KanbanController::class, 'storeChecklistItem'])->name('cards.checklists.store');
    Route::put('/cards/{card}/checklists/{item}', [KanbanController::class, 'updateChecklistItem'])->name('cards.checklists.update');
    Route::delete('/cards/{card}/checklists/{item}', [KanbanController::class, 'destroyChecklistItem'])->name('cards.checklists.destroy');

    Route::post('/cards/{card}/comments', [KanbanController::class, 'storeComment'])->name('cards.comments.store');
    Route::delete('/comments/{comment}', [KanbanController::class, 'destroyComment'])->name('comments.destroy');

    Route::post('/cards/{card}/attachments', [KanbanController::class, 'storeAttachment'])->name('cards.attachments.store');
    Route::delete('/cards/{card}/attachments/{mediaId}', [KanbanController::class, 'destroyAttachment'])->name('cards.attachments.destroy');
});
