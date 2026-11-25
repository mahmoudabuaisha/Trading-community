<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortfolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->mine($request->user());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->portfolioProjects()->count() >= 60, 422, __('validation.custom.portfolio.limit'));
        $this->fill($request, $user->portfolioProjects()->make(), 'required');

        return $this->mine($user, 201);
    }

    /** POST, not PATCH: the update may carry a new picture as multipart form data. */
    public function update(Request $request, PortfolioProject $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 404);
        $this->fill($request, $project, 'sometimes');

        return $this->mine($request->user());
    }

    public function destroy(Request $request, PortfolioProject $project): Response
    {
        abort_unless($project->user_id === $request->user()->id, 404);
        $project->delete();

        return response()->noContent();
    }

    /** Who may open the portfolio; `newLink` replaces the address so an old link stops working. */
    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate(['visibility' => ['sometimes', Rule::in(PortfolioProject::VISIBILITIES)], 'newLink' => ['sometimes', 'boolean']]);
        $user = $request->user();
        if (isset($data['visibility'])) {
            $user->portfolio_visibility = $data['visibility'];
        }
        if (($data['newLink'] ?? false) || ($user->portfolio_visibility !== 'private' && ! $user->portfolio_slug)) {
            $user->portfolio_slug = Str::lower(Str::random(20));
        }
        $user->save();

        return $this->mine($user);
    }

    /** The page behind a shared link. Private portfolios answer exactly like missing ones. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $owner = User::where('portfolio_slug', $slug)->first();
        $viewer = $request->user('sanctum');
        $allowed = $owner && ($owner->portfolio_visibility === 'public' || ($owner->portfolio_visibility === 'members' && $viewer) || $viewer?->id === $owner->id);
        abort_unless($allowed, 404);

        return response()->json([
            'owner' => ['displayName' => $owner->display_name, 'avatarUrl' => $owner->avatar_url, 'bio' => $owner->bio, 'specialties' => $owner->specialties ?? []],
            'projects' => $owner->portfolioProjects()->orderByDesc('completed_on')->orderByDesc('id')->get()->map(fn (PortfolioProject $project): array => $project->toApi()),
        ]);
    }

    /** Pictures of vision boards and portfolios, stored under unguessable names. */
    public function media(string $directory, string $file): BinaryFileResponse
    {
        $path = $directory.'/'.$file;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    private function fill(Request $request, PortfolioProject $project, string $required): void
    {
        if ($request->has('removeImage')) {
            $request->merge(['removeImage' => filter_var($request->input('removeImage'), FILTER_VALIDATE_BOOLEAN)]);
        }
        $data = $request->validate([
            'title' => [$required, 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', Rule::in(PortfolioProject::CATEGORIES)],
            'skills' => ['sometimes', 'array', 'max:12'],
            'skills.*' => ['string', 'max:40'],
            // Only web links: never javascript: or data: addresses.
            'link' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'completedOn' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'removeImage' => ['sometimes', 'boolean'],
        ]);
        $old = $project->image_path;
        $project->fill(array_filter(['title' => $data['title'] ?? null, 'category' => $data['category'] ?? null], fn ($value): bool => $value !== null));
        foreach (['description' => '', 'skills' => [], 'link' => null, 'completedOn' => null] as $key => $empty) {
            if (array_key_exists($key, $data)) {
                $project->{Str::snake($key)} = $data[$key] ?? $empty;
            }
        }
        if ($request->file('image')) {
            $project->image_path = $request->file('image')->store(PortfolioProject::IMAGE_DIRECTORY, 'public');
        } elseif ($data['removeImage'] ?? false) {
            $project->image_path = null;
        }
        $project->save();
        if ($old && $old !== $project->image_path) {
            Storage::disk('public')->delete($old);
        }
    }

    private function mine(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'projects' => $user->portfolioProjects()->orderByDesc('completed_on')->orderByDesc('id')->get()->map(fn (PortfolioProject $project): array => $project->toApi()),
            'visibility' => $user->portfolio_visibility ?? 'private',
            'slug' => $user->portfolio_slug,
        ], $status);
    }
}
