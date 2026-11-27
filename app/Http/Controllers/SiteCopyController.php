<?php

namespace App\Http\Controllers;

use App\Models\SiteCopy;
use App\Services\AdminService;
use App\Services\Translation\AutoTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Phrases of the login and home pages that administrators rewrite. Members
 * (and visitors, on the login page) get each one in their own language.
 */
class SiteCopyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['copy' => SiteCopy::all()->mapWithKeys(fn (SiteCopy $copy): array => [$copy->key => $copy->tr('text')])]);
    }

    public function adminIndex(): JsonResponse
    {
        Gate::authorize('area', 'content');

        return response()->json($this->adminPayload());
    }

    /** Save one phrase; an empty text puts the built-in phrase back. */
    public function save(Request $request, AdminService $admin): JsonResponse
    {
        Gate::authorize('area', 'content');
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^('.implode('|', SiteCopy::SECTIONS).')\.[A-Za-z0-9_.]+$/'],
            'text' => ['present', 'nullable', 'string', 'max:500'],
        ]);
        $text = trim((string) $data['text']);
        $copy = SiteCopy::where('key', $data['key'])->first();
        if ($text === '') {
            $copy?->delete();
        } else {
            $copy ??= new SiteCopy(['key' => $data['key']]);
            $copy->text = $text;
            $copy->save();
        }
        $admin->record($request->user(), 'site_copy_updated', $data['key']);

        return response()->json($this->adminPayload());
    }

    private function adminPayload(): array
    {
        return [
            'copy' => SiteCopy::orderBy('key')->get()->mapWithKeys(fn (SiteCopy $copy): array => [$copy->key => $copy->text]),
            // Without a key, phrases show as written in every language.
            'autoTranslate' => AutoTranslator::enabled(),
        ];
    }
}
