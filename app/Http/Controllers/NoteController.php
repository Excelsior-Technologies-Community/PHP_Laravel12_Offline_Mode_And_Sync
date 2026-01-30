<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    public function sync(Request $request)
    {
        $notes = $request->notes ?? [];

        foreach ($notes as $note) {

            // Skip invalid notes
            if (empty($note['title']) || empty($note['content'])) {
                continue;
            }

            \App\Models\Note::create([
                'title' => $note['title'],
                'content' => $note['content'],
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'synced']);
    }

}
