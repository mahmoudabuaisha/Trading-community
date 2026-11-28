<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The platform's official social accounts (Instagram, Telegram…), shown on the sign-in page and in the menu. */
class OfficialLinksController extends Controller
{
    public const KINDS = ['instagram', 'telegram', 'other'];

    public static function links(): array
    {
        $stored = json_decode((string) Setting::where('key', 'official_links')->value('value'), true);

        return is_array($stored) ? array_values($stored) : [];
    }

    public function index(): JsonResponse
    {
        return response()->json(['links' => self::links()]);
    }

    public function update(Request $request, AdminService $admin): JsonResponse
    {
        Gate::authorize('area', 'content');
        $data = $request->validate([
            'links' => ['present', 'array', 'max:12'],
            'links.*.kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'links.*.label' => ['required', 'string', 'max:40'],
            // Only secure web links: no javascript: or other schemes.
            'links.*.url' => ['required', 'string', 'max:300', 'url:https'],
        ]);
        $links = array_map(fn (array $link): array => ['kind' => $link['kind'], 'label' => trim($link['label']), 'url' => trim($link['url'])], $data['links']);
        Setting::updateOrCreate(['key' => 'official_links'], ['value' => json_encode($links)]);
        $admin->record($request->user(), 'official_links_updated', (string) count($links));

        return response()->json(['links' => $links]);
    }
}
