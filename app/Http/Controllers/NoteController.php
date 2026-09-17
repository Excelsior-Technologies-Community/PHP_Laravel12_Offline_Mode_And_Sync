<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class NoteController extends Controller
{
    /**
     * Return all server notes.
     */
    public function index(): JsonResponse
    {
        $notes = Note::orderByDesc('updated_at')->get([
            'id',
            'client_id',
            'title',
            'content',
            'created_at',
            'updated_at',
            'sync_attempts',
            'last_sync_error',
            'last_synced_at',
        ]);

        return response()->json([
            'status' => 'success',
            'notes' => $notes,
        ]);
    }

    /**
     * Sync offline notes with the server.
     *
     * This method:
     * - prevents duplicate notes using client_id
     * - detects conflicts
     * - updates existing notes safely
     * - records sync attempts
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'notes' => ['required', 'array'],
            'notes.*.client_id' => ['required', 'string', 'max:100'],
            'notes.*.title' => ['required', 'string', 'max:255'],
            'notes.*.content' => ['required', 'string'],
            'notes.*.updated_at' => ['nullable', 'date'],
        ]);

        $synced = [];
        $conflicts = [];
        $failed = [];

        foreach ($request->notes as $incomingNote) {
            try {
                DB::transaction(function () use (
                    $incomingNote,
                    &$synced,
                    &$conflicts
                ) {
                    $clientId = $incomingNote['client_id'];

                    $existingNote = Note::where('client_id', $clientId)
                        ->lockForUpdate()
                        ->first();

                    /*
                     * New note.
                     */
                    if (!$existingNote) {
                        $note = Note::create([
                            'client_id' => $clientId,
                            'title' => $incomingNote['title'],
                            'content' => $incomingNote['content'],
                            'sync_attempts' => 1,
                            'last_sync_error' => null,
                            'last_synced_at' => now(),
                        ]);

                        $synced[] = [
                            'client_id' => $clientId,
                            'server_id' => $note->id,
                        ];

                        return;
                    }

                    /*
                     * Existing note:
                     * compare local timestamp with server timestamp.
                     */
                    $localUpdatedAt = isset($incomingNote['updated_at'])
                        ? Carbon::parse($incomingNote['updated_at'])
                        : now();

                    $serverUpdatedAt = $existingNote->updated_at;

                    $contentChanged =
                        $existingNote->title !== $incomingNote['title'] ||
                        $existingNote->content !== $incomingNote['content'];

                    /*
                     * Conflict:
                     * Server has a newer version and content is different.
                     */
                    if (
                        $serverUpdatedAt &&
                        $serverUpdatedAt->greaterThan($localUpdatedAt) &&
                        $contentChanged
                    ) {
                        $existingNote->increment('sync_attempts');

                        $conflicts[] = [
                            'client_id' => $clientId,
                            'server_id' => $existingNote->id,
                            'local' => [
                                'client_id' => $clientId,
                                'title' => $incomingNote['title'],
                                'content' => $incomingNote['content'],
                                'updated_at' => $localUpdatedAt->toISOString(),
                            ],
                            'server' => [
                                'id' => $existingNote->id,
                                'client_id' => $existingNote->client_id,
                                'title' => $existingNote->title,
                                'content' => $existingNote->content,
                                'updated_at' => $existingNote->updated_at?->toISOString(),
                            ],
                        ];

                        return;
                    }

                    /*
                     * No conflict.
                     * Update existing server record.
                     */
                    $existingNote->update([
                        'title' => $incomingNote['title'],
                        'content' => $incomingNote['content'],
                        'sync_attempts' => $existingNote->sync_attempts + 1,
                        'last_sync_error' => null,
                        'last_synced_at' => now(),
                    ]);

                    $synced[] = [
                        'client_id' => $clientId,
                        'server_id' => $existingNote->id,
                    ];
                });
            } catch (Throwable $e) {
                $failed[] = [
                    'client_id' => $incomingNote['client_id'] ?? null,
                    'error' => $e->getMessage(),
                ];

                if (!empty($incomingNote['client_id'])) {
                    Note::where('client_id', $incomingNote['client_id'])
                        ->update([
                            'last_sync_error' => $e->getMessage(),
                        ]);
                }
            }
        }

        return response()->json([
            'status' => 'completed',
            'synced' => $synced,
            'conflicts' => $conflicts,
            'failed' => $failed,
        ]);
    }

    /**
     * Keep the local version during a conflict.
     */
    public function resolveKeepLocal(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $note = Note::where('client_id', $request->client_id)->first();

        if (!$note) {
            $note = Note::create([
                'client_id' => $request->client_id,
                'title' => $request->title,
                'content' => $request->content,
                'sync_attempts' => 1,
                'last_sync_error' => null,
                'last_synced_at' => now(),
            ]);
        } else {
            $note->update([
                'title' => $request->title,
                'content' => $request->content,
                'sync_attempts' => $note->sync_attempts + 1,
                'last_sync_error' => null,
                'last_synced_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'resolved',
            'resolution' => 'local',
            'note' => $note,
        ]);
    }

    /**
     * Keep the server version during a conflict.
     */
    public function resolveKeepServer(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'string', 'max:100'],
        ]);

        $note = Note::where('client_id', $request->client_id)->first();

        if (!$note) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server note not found.',
            ], 404);
        }

        $note->update([
            'last_sync_error' => null,
            'last_synced_at' => now(),
            'sync_attempts' => $note->sync_attempts + 1,
        ]);

        return response()->json([
            'status' => 'resolved',
            'resolution' => 'server',
            'note' => $note,
        ]);
    }
}