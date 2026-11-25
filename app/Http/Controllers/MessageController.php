<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Broadcast;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A member's side of their private thread with the administrators. */
class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Opening the thread reads everything the administrators sent.
        $user->messages()->unreadFor(admin: false)->update(['read_at' => now()]);

        return response()->json([
            'messages' => MessageResource::collection($user->messages()->with('sender')->oldest()->oldest('id')->get()),
        ]);
    }

    public function store(MessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $message = $user->messages()->create(['sender_id' => $user->id, 'from_admin' => false, 'body' => $request->validated('body')]);

        return response()->json(['message' => new MessageResource($message->load('sender'))], 201);
    }

    /** Badge counts: what this member has not read, and (for admins) what members are waiting on. */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            // Today's messages from the administrators ride along, so an open tab receives one sent just now.
            'broadcasts' => Broadcast::forDay(now($user->timezone)->toDateString()),
            'member' => $user->messages()->unreadFor(admin: false)->count(),
            'admin' => $user->isAdmin() ? Message::unreadFor(admin: true)->count() : 0,
        ]);
    }
}
