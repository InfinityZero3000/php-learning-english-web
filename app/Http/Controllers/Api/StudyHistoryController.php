<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class StudyHistoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = DB::table('study_history')->where('user_id', request()->user()->id);
        if (request('activity_type')) {
            $query->where('activity_type', request('activity_type'));
        } if (request('from')) {
            $query->whereDate('created_at', '>=', request('from'));
        } if (request('to')) {
            $query->whereDate('created_at', '<=', request('to'));
        }

return JsonResource::collection($query->latest('created_at')->paginate());
    }
}
