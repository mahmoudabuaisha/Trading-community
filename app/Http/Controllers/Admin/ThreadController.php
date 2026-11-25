<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The administrators' side of members' private threads. */
class ThreadController extends Controller
{
    /** Every thread with at least one message, most recent first, with its unread count. */
    public function index(): JsonResponse
    {
        Gate::authorize('area', 'messages');

        $latestIds = Message::selectRaw('MAX(id) as id')->groupBy('user_id')->pluck('id');
        $unread = Message::unreadFor(admin: true)->selectRaw('user_id, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        $threads = Message::with('user')->whereIn('id', $latestIds)->latest()->latest('id')->get()
            ->map(fn (Message $message): array => [
                'user' => ['id' => $message->user->id, 'displayName' => $message->user->display_name, 'email' => $message->user->email],
                'lastMessage' => new MessageResource($message),
                'unread' => (int) ($unread[$message->user_id] ?? 0),
            ]);

        return response()->json(['threads' => $threads]);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('area', 'messages');
        // Opening the thread reads everything the member sent.
        $user->messages()->unreadFor(admin: true)->update(['read_at' => now()]);

        return response()->json([
            'user' => ['id' => $user->id, 'displayName' => $user->display_name, 'email' => $user->email],
            'messages' => MessageResource::collection($user->messages()->with('sender')->oldest()->oldest('id')->get()),
        ]);
    }

    public function store(MessageRequest $request, User $user): JsonResponse
    {
        Gate::authorize('area', 'messages');
        $message = $user->messages()->create(['sender_id' => $request->user()->id, 'from_admin' => true, 'body' => $request->validated('body')]);

        return response()->json(['message' => new MessageResource($message->load('sender'))], 201);
    }
}
