<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The books section: every member sees the shelf; a book opens only for members it was granted to. */
class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $granted = $user->isStaff() ? null : $user->grantedIds('book');

        return response()->json(['books' => Book::where('is_published', true)->orderBy('position')->orderBy('id')->get()
            ->map(fn (Book $book): array => self::present($book, $granted === null || in_array($book->id, $granted, true)))]);
    }

    /** The book's file, shown in the browser. Members it was not opened for get the same answer as for a missing book. */
    public function read(Request $request, Book $book): BinaryFileResponse
    {
        abort_unless($book->is_published || $request->user()->isStaff(), 404);
        abort_unless($book->file_path && $request->user()->hasGrant('book', $book->id), 404);
        abort_unless(Storage::disk('local')->exists($book->file_path), 404);

        return response()->file(Storage::disk('local')->path($book->file_path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($book->file_name ?: 'book.pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function cover(string $file): BinaryFileResponse
    {
        $path = Book::COVER_DIRECTORY.'/'.$file;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public static function present(Book $book, bool $unlocked, bool $admin = false): array
    {
        return [
            'id' => $book->id,
            'title' => $admin ? $book->title : $book->tr('title'),
            'author' => $book->author,
            'description' => $admin ? $book->description : $book->tr('description'),
            'coverUrl' => $book->cover_path ? '/media/'.$book->cover_path : null,
            'hasFile' => $book->file_path !== null,
            'fileName' => $unlocked ? $book->file_name : null,
            'position' => $book->position,
            'isPublished' => $book->is_published,
            'unlocked' => $unlocked,
        ];
    }
}
