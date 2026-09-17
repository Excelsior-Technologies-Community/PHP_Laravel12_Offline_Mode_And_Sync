<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\SyncHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NoteController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1. LIST / SEARCH NOTES
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $query = Note::query();

        /*
         * Search
         */
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        /*
         * Favorite filter
         */
        if ($request->has('favorite')) {
            $query->where(
                'is_favorite',
                filter_var(
                    $request->favorite,
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        /*
         * Pinned filter
         */
        if ($request->has('pinned')) {
            $query->where(
                'is_pinned',
                filter_var(
                    $request->pinned,
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        /*
         * Tag filter
         */
        if ($request->filled('tag')) {
            $query->where(
                'tags',
                'like',
                '%' . $request->tag . '%'
            );
        }

        $notes = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_favorite')
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'client_id',
                'title',
                'content',
                'tags',
                'is_favorite',
                'is_pinned',
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


    /*
    |--------------------------------------------------------------------------
    | 2. SYNC
    |--------------------------------------------------------------------------
    */

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'notes' => ['required', 'array'],

            'notes.*.client_id' => [
                'required',
                'string',
                'max:100'
            ],

            'notes.*.title' => [
                'required',
                'string',
                'max:255'
            ],

            'notes.*.content' => [
                'required',
                'string'
            ],

            'notes.*.tags' => [
                'nullable',
                'string'
            ],

            'notes.*.is_favorite' => [
                'nullable',
                'boolean'
            ],

            'notes.*.is_pinned' => [
                'nullable',
                'boolean'
            ],

            'notes.*.updated_at' => [
                'nullable',
                'date'
            ],

            'notes.*.deleted' => [
                'nullable',
                'boolean'
            ],
        ]);

        $synced = [];
        $conflicts = [];
        $failed = [];
        $deleted = [];

        foreach ($request->notes as $incomingNote) {
            try {
                DB::transaction(function () use (
                    $incomingNote,
                    &$synced,
                    &$conflicts,
                    &$deleted
                ) {
                    $clientId = $incomingNote['client_id'];

                    $existingNote = Note::withTrashed()
                        ->where('client_id', $clientId)
                        ->lockForUpdate()
                        ->first();

                    /*
                     * Offline deletion
                     */
                    if (
                        !empty($incomingNote['deleted'])
                        && $existingNote
                    ) {
                        if (!$existingNote->trashed()) {
                            $existingNote->delete();
                        }

                        $deleted[] = [
                            'client_id' => $clientId,
                            'server_id' => $existingNote->id,
                        ];

                        return;
                    }

                    /*
                     * New note
                     */
                    if (!$existingNote) {
                        $note = Note::create([
                            'client_id' => $clientId,
                            'title' => $incomingNote['title'],
                            'content' => $incomingNote['content'],
                            'tags' => $incomingNote['tags'] ?? null,
                            'is_favorite' => $incomingNote['is_favorite'] ?? false,
                            'is_pinned' => $incomingNote['is_pinned'] ?? false,
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
                     * Restore soft deleted record if needed.
                     */
                    if ($existingNote->trashed()) {
                        $existingNote->restore();
                    }

                    $localUpdatedAt = isset(
                        $incomingNote['updated_at']
                    )
                        ? Carbon::parse(
                            $incomingNote['updated_at']
                        )
                        : now();

                    $serverUpdatedAt =
                        $existingNote->updated_at;

                    $contentChanged =
                        $existingNote->title !==
                            $incomingNote['title']
                        ||
                        $existingNote->content !==
                            $incomingNote['content'];

                    /*
                     * Conflict detection
                     */
                    if (
                        $serverUpdatedAt &&
                        $serverUpdatedAt->greaterThan(
                            $localUpdatedAt
                        ) &&
                        $contentChanged
                    ) {
                        $existingNote->increment(
                            'sync_attempts'
                        );

                        $conflicts[] = [
                            'client_id' => $clientId,

                            'server_id' =>
                                $existingNote->id,

                            'local' => [
                                'client_id' => $clientId,
                                'title' =>
                                    $incomingNote['title'],
                                'content' =>
                                    $incomingNote['content'],
                                'tags' =>
                                    $incomingNote['tags'] ?? null,
                                'is_favorite' =>
                                    $incomingNote['is_favorite']
                                    ?? false,
                                'is_pinned' =>
                                    $incomingNote['is_pinned']
                                    ?? false,
                                'updated_at' =>
                                    $localUpdatedAt
                                        ->toISOString(),
                            ],

                            'server' => [
                                'id' =>
                                    $existingNote->id,

                                'client_id' =>
                                    $existingNote->client_id,

                                'title' =>
                                    $existingNote->title,

                                'content' =>
                                    $existingNote->content,

                                'tags' =>
                                    $existingNote->tags,

                                'is_favorite' =>
                                    $existingNote->is_favorite,

                                'is_pinned' =>
                                    $existingNote->is_pinned,

                                'updated_at' =>
                                    $existingNote
                                        ->updated_at
                                        ?->toISOString(),
                            ],
                        ];

                        return;
                    }

                    /*
                     * Normal update
                     */
                    $existingNote->update([
                        'title' =>
                            $incomingNote['title'],

                        'content' =>
                            $incomingNote['content'],

                        'tags' =>
                            $incomingNote['tags'] ?? null,

                        'is_favorite' =>
                            $incomingNote['is_favorite']
                            ?? false,

                        'is_pinned' =>
                            $incomingNote['is_pinned']
                            ?? false,

                        'sync_attempts' =>
                            $existingNote
                                ->sync_attempts + 1,

                        'last_sync_error' => null,

                        'last_synced_at' => now(),
                    ]);

                    $synced[] = [
                        'client_id' => $clientId,
                        'server_id' =>
                            $existingNote->id,
                    ];
                });

            } catch (Throwable $e) {

                $failed[] = [
                    'client_id' =>
                        $incomingNote['client_id'] ?? null,

                    'error' =>
                        $e->getMessage(),
                ];

                if (
                    !empty(
                        $incomingNote['client_id']
                    )
                ) {
                    Note::withTrashed()
                        ->where(
                            'client_id',
                            $incomingNote['client_id']
                        )
                        ->update([
                            'last_sync_error' =>
                                $e->getMessage(),
                        ]);
                }
            }
        }

        /*
         * Save synchronization history
         */
        SyncHistory::create([
            'operation' => 'sync',
            'total' => count($request->notes),
            'synced' => count($synced),
            'failed' => count($failed),
            'conflicts' => count($conflicts),
            'deleted' => count($deleted),
            'status' =>
                count($failed) > 0
                    ? 'partial'
                    : 'completed',
        ]);

        return response()->json([
            'status' => 'completed',
            'synced' => $synced,
            'conflicts' => $conflicts,
            'failed' => $failed,
            'deleted' => $deleted,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 3. KEEP LOCAL
    |--------------------------------------------------------------------------
    */

    public function resolveKeepLocal(
        Request $request
    ): JsonResponse {
        $request->validate([
            'client_id' => [
                'required',
                'string',
                'max:100'
            ],

            'title' => [
                'required',
                'string',
                'max:255'
            ],

            'content' => [
                'required',
                'string'
            ],

            'tags' => [
                'nullable',
                'string'
            ],

            'is_favorite' => [
                'nullable',
                'boolean'
            ],

            'is_pinned' => [
                'nullable',
                'boolean'
            ],
        ]);

        $note = Note::withTrashed()
            ->where(
                'client_id',
                $request->client_id
            )
            ->first();

        if (!$note) {

            $note = Note::create([
                'client_id' =>
                    $request->client_id,

                'title' =>
                    $request->title,

                'content' =>
                    $request->content,

                'tags' =>
                    $request->tags,

                'is_favorite' =>
                    $request->boolean(
                        'is_favorite'
                    ),

                'is_pinned' =>
                    $request->boolean(
                        'is_pinned'
                    ),

                'sync_attempts' => 1,

                'last_sync_error' => null,

                'last_synced_at' => now(),
            ]);

        } else {

            if ($note->trashed()) {
                $note->restore();
            }

            $note->update([
                'title' =>
                    $request->title,

                'content' =>
                    $request->content,

                'tags' =>
                    $request->tags,

                'is_favorite' =>
                    $request->boolean(
                        'is_favorite'
                    ),

                'is_pinned' =>
                    $request->boolean(
                        'is_pinned'
                    ),

                'sync_attempts' =>
                    $note->sync_attempts + 1,

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


    /*
    |--------------------------------------------------------------------------
    | 4. KEEP SERVER
    |--------------------------------------------------------------------------
    */

    public function resolveKeepServer(
        Request $request
    ): JsonResponse {
        $request->validate([
            'client_id' => [
                'required',
                'string',
                'max:100'
            ],
        ]);

        $note = Note::where(
            'client_id',
            $request->client_id
        )->first();

        if (!$note) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Server note not found.',
            ], 404);
        }

        $note->update([
            'last_sync_error' => null,

            'last_synced_at' => now(),

            'sync_attempts' =>
                $note->sync_attempts + 1,
        ]);

        return response()->json([
            'status' => 'resolved',
            'resolution' => 'server',
            'note' => $note,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 5. DELETE NOTE
    |--------------------------------------------------------------------------
    */

    public function destroy(
        string $clientId
    ): JsonResponse {
        $note = Note::where(
            'client_id',
            $clientId
        )->first();

        if (!$note) {
            return response()->json([
                'status' => 'error',
                'message' => 'Note not found.',
            ], 404);
        }

        $note->delete();

        return response()->json([
            'status' => 'deleted',
            'client_id' => $clientId,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 6. FAVORITE
    |--------------------------------------------------------------------------
    */

    public function toggleFavorite(
        string $clientId
    ): JsonResponse {
        $note = Note::where(
            'client_id',
            $clientId
        )->firstOrFail();

        $note->update([
            'is_favorite' =>
                !$note->is_favorite,
        ]);

        return response()->json([
            'status' => 'success',
            'is_favorite' =>
                $note->is_favorite,
            'note' => $note,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 7. PIN
    |--------------------------------------------------------------------------
    */

    public function togglePin(
        string $clientId
    ): JsonResponse {
        $note = Note::where(
            'client_id',
            $clientId
        )->firstOrFail();

        $note->update([
            'is_pinned' =>
                !$note->is_pinned,
        ]);

        return response()->json([
            'status' => 'success',
            'is_pinned' =>
                $note->is_pinned,
            'note' => $note,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 8. STATISTICS
    |--------------------------------------------------------------------------
    */

    public function statistics(): JsonResponse
    {
        $total = Note::count();

        $favorites = Note::where(
            'is_favorite',
            true
        )->count();

        $pinned = Note::where(
            'is_pinned',
            true
        )->count();

        $failed = Note::whereNotNull(
            'last_sync_error'
        )->count();

        $synced = Note::whereNotNull(
            'last_synced_at'
        )->count();

        $deleted = Note::onlyTrashed()->count();

        $conflicts = Note::where(
            'last_sync_error',
            'like',
            '%conflict%'
        )->count();

        return response()->json([
            'status' => 'success',

            'statistics' => [
                'total' => $total,
                'favorites' => $favorites,
                'pinned' => $pinned,
                'failed' => $failed,
                'synced' => $synced,
                'deleted' => $deleted,
                'conflicts' => $conflicts,
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 9. SYNC HISTORY
    |--------------------------------------------------------------------------
    */

    public function history(): JsonResponse
    {
        $history = SyncHistory::oldest()
            ->limit(50)
            ->get();

        return response()->json([
            'status' => 'success',
            'history' => $history,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORT CSV
    |--------------------------------------------------------------------------
    */

  public function export()
{
    $notes = Note::withTrashed()
        ->orderByDesc('id')
        ->get();

    $fileName = 'notes_' . now()->format('Y_m_d_H_i_s') . '.csv';

    $headers = [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Expires' => '0',
    ];

    $callback = function () use ($notes) {
        $handle = fopen('php://output', 'w');

        fputcsv($handle, [
            'ID',
            'Client ID',
            'Title',
            'Content',
            'Tags',
            'Favorite',
            'Pinned',
            'Created At',
            'Updated At',
            'Last Synced At',
        ]);

        foreach ($notes as $note) {
            $tags = $note->tags;

            if (is_array($tags)) {
                $tags = implode(', ', $tags);
            } elseif (is_null($tags)) {
                $tags = '';
            } else {
                $decodedTags = json_decode($tags, true);

                if (is_array($decodedTags)) {
                    $tags = implode(', ', $decodedTags);
                } else {
                    $tags = (string) $tags;
                }
            }

            fputcsv($handle, [
                $note->id,
                $note->client_id,
                $note->title,
                $note->content,
                $tags,
                $note->is_favorite ? 'Yes' : 'No',
                $note->is_pinned ? 'Yes' : 'No',
                optional($note->created_at)->format('Y-m-d H:i:s'),
                optional($note->updated_at)->format('Y-m-d H:i:s'),
                optional($note->last_synced_at)->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($handle);
    };

    return response()->stream($callback, 200, $headers);
}
}