<?php

namespace App\Http\Controllers;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\Assistant\AssistantClient;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The AI assistant: a paid service an administrator opens per member in
 * their subscription. Conversations are not stored; the member's browser
 * sends the recent turns each time.
 */
class AssistantController extends Controller
{
    /** Whether this member may ask, and how many questions are left today. */
    public function status(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()));
    }

    public function ask(Request $request, AssistantClient $client, PointsService $points, AchievementService $achievements): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);
        $messages = array_values(array_map(fn (array $turn): array => ['role' => $turn['role'], 'content' => trim($turn['content'])], $data['messages']));
        abort_unless(end($messages)['role'] === 'user', 422);

        $state = $this->state($user);
        abort_unless($state['configured'], 503, __('validation.custom.assistant.off'));
        abort_unless($state['allowed'], 403, __('validation.custom.assistant.subscribers'));
        abort_if($state['remaining'] <= 0, 429, __('validation.custom.assistant.limit', ['n' => $state['limit']]));

        // Count the question before asking, so parallel requests cannot pass the limit.
        $day = now($user->timezone)->toDateString();
        DB::table('assistant_usages')->upsert([['user_id' => $user->id, 'day' => $day, 'count' => 1, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'day'], ['count' => DB::raw('count + 1'), 'updated_at' => now()]);

        try {
            $reply = $client->reply($this->system($user, $points, $achievements), $messages);
        } catch (RateLimitException|APIConnectionException $e) {
            $this->refund($user, $day);
            abort(503, __('validation.custom.assistant.busy'));
        } catch (AuthenticationException $e) {
            $this->refund($user, $day);
            Log::error('Assistant API key rejected', ['message' => $e->getMessage()]);
            abort(503, __('validation.custom.assistant.off'));
        } catch (APIStatusException $e) {
            $this->refund($user, $day);
            Log::warning('Assistant request failed', ['status' => $e->status ?? null, 'type' => $e->type?->value]);
            abort(503, __('validation.custom.assistant.busy'));
        }

        DB::table('assistant_usages')->where('user_id', $user->id)->whereDate('day', $day)->update([
            'input_tokens' => DB::raw('input_tokens + '.$reply->inputTokens),
            'output_tokens' => DB::raw('output_tokens + '.$reply->outputTokens),
        ]);

        return response()->json([
            'reply' => $reply->refused || $reply->text === '' ? __('validation.custom.assistant.refused') : $reply->text,
            ...$this->state($user),
        ]);
    }

    private function refund(User $user, string $day): void
    {
        DB::table('assistant_usages')->where('user_id', $user->id)->whereDate('day', $day)->where('count', '>', 0)->decrement('count');
    }

    /** @return array{configured: bool, allowed: bool, limit: int, remaining: int} */
    private function state(User $user): array
    {
        $limit = (int) config('assistant.daily_limit');
        $used = (int) DB::table('assistant_usages')->where('user_id', $user->id)->whereDate('day', now($user->timezone)->toDateString())->value('count');

        return [
            'configured' => filled(config('assistant.api_key')),
            // Staff may try it; members need the service opened in their subscription.
            'allowed' => $user->hasGrant('assistant', 0),
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
        ];
    }

    /**
     * The instructions (fixed, cached) and a short picture of the member's
     * situation (changes per request, after the cache breakpoint).
     */
    private function system(User $user, PointsService $points, AchievementService $achievements): array
    {
        $guide = <<<'TXT'
        You are the personal assistant inside SN Tasks, a productivity platform for an Arabic-speaking community of traders, students and freelancers. Members plan daily, weekly, monthly and yearly tasks, set goals with roadmaps (stages and steps), track habits, earn points from achievements set by the team, spend points in a store (titles, frames, themes, designs), follow skill paths with quizzes, keep a vision board, a growth timeline and a portfolio, and read lessons and books.

        How to help:
        - Answer in the member's language (Arabic by default; match the language they write in). Keep replies short and practical: a few sentences or a short list.
        - Use the member's situation below to make advice specific (their tasks today, goals, streak, strongest times). Do not invent data that is not there.
        - Point to the right part of the platform when useful (for example "Habit tracker" or "Goal roadmap") without promising features that do not exist.
        - For trading, discuss planning, discipline, risk management and learning habits. Do not give signals, price predictions or personalised financial advice; say that decisions and risk are the member's own.
        - For health, legal or emotional crises, encourage them to reach a qualified person.
        TXT;

        $today = now($user->timezone)->toDateString();
        $tasks = $user->tasks()->where('period_type', 'daily')->whereDate('period_start', $today)->get(['title', 'status', 'priority']);
        $goals = $user->goals()->where('status', '!=', 'completed')->latest()->limit(5)->get(['title', 'current_value', 'target_value']);
        $situation = [
            'name' => $user->display_name,
            'specialties' => $user->specialties ?? [],
            'today' => $today,
            'today_tasks' => $tasks->map(fn (Task $task): string => "{$task->title} [{$task->status}, {$task->priority}]")->all(),
            'open_goals' => $goals->map(fn ($goal): string => "{$goal->title} ({$goal->current_value}/{$goal->target_value})")->all(),
            'streak_days' => $achievements->currentStreak($user),
            'points_balance' => $points->balance($user),
            'tasks_completed_total' => $user->tasks()->where('status', Task::STATUS_COMPLETED)->count(),
        ];

        return [
            ['type' => 'text', 'text' => $guide, 'cacheControl' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => "The member's current situation (JSON):\n".json_encode($situation, JSON_UNESCAPED_UNICODE)],
        ];
    }
}
