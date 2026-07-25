<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookmarkRequest;
use App\Http\Resources\BookmarkResource;
use App\Models\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookmarkController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = DB::table('vocabulary_bookmarks')->join('vocabularies', 'vocabularies.id', '=', 'vocabulary_bookmarks.vocabulary_id')->where('vocabulary_bookmarks.user_id', request()->user()->id)->select('vocabulary_bookmarks.*', 'vocabularies.word', 'vocabularies.meaning', 'vocabularies.topic_id');
        if (request('topic_id')) {
            $query->where('vocabularies.topic_id', request('topic_id'));
        }

        return BookmarkResource::collection($query->latest('vocabulary_bookmarks.created_at')->paginate());
    }

    public function store(StoreBookmarkRequest $request, Vocabulary $vocabulary): JsonResponse|Response
    {
        $existing = DB::table('vocabulary_bookmarks')->where('user_id', $request->user()->id)->where('vocabulary_id', $vocabulary->id);
        if ($existing->exists()) {
            $existing->delete();

            return response()->noContent();
        } DB::table('vocabulary_bookmarks')->insert(['user_id' => $request->user()->id, 'vocabulary_id' => $vocabulary->id, 'note' => $request->validated('note'), 'created_at' => now()]);

        return (new BookmarkResource((object) ['id' => DB::getPdo()->lastInsertId(), 'vocabulary_id' => $vocabulary->id, 'note' => $request->validated('note'), 'created_at' => now(), 'word' => $vocabulary->word, 'meaning' => $vocabulary->meaning, 'topic_id' => $vocabulary->topic_id]))->response()->setStatusCode(201);
    }

    public function destroy(Vocabulary $vocabulary): Response
    {
        DB::table('vocabulary_bookmarks')->where('user_id', request()->user()->id)->where('vocabulary_id', $vocabulary->id)->delete();

        return response()->noContent();
    }
}
