<?php

namespace App\Http\Controllers;

use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'lessons' => LessonResource::collection(Lesson::where('is_published', true)->orderBy('position')->orderBy('id')->get()),
        ]);
    }

    /** Serves a lesson image from the public disk, so no storage symlink is needed on the server. */
    public function image(string $file): BinaryFileResponse
    {
        $path = Lesson::IMAGE_DIRECTORY.'/'.$file;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
