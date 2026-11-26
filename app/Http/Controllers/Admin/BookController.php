<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BookController as MemberBookController;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/** Books are written up by whoever manages education. */
class BookController extends Controller
{
    public function index(): JsonResponse
    {
        return self::all();
    }

    public function store(Request $request, AdminService $admin): JsonResponse
    {
        $book = new Book;
        $book->created_by = $request->user()->id;
        $book->position = (int) Book::max('position') + 1;
        $this->fill($request, $book, 'required');
        $admin->record($request->user(), 'book_created', $book->title);

        return self::all(201);
    }

    /** POST, not PATCH: the update may carry a new cover or file as multipart form data. */
    public function update(Request $request, Book $book, AdminService $admin): JsonResponse
    {
        $this->fill($request, $book, 'sometimes');
        $admin->record($request->user(), 'book_updated', $book->title);

        return self::all();
    }

    public function destroy(Request $request, Book $book, AdminService $admin): Response
    {
        $book->delete();
        $admin->record($request->user(), 'book_deleted', $book->title);

        return response()->noContent();
    }

    private function fill(Request $request, Book $book, string $required): void
    {
        foreach (['isPublished', 'removeCover'] as $flag) {
            if ($request->has($flag)) {
                $request->merge([$flag => filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
        $data = $request->validate([
            'title' => [$required, 'string', 'max:160'],
            'author' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'isPublished' => ['sometimes', 'boolean'],
            'cover' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'removeCover' => ['sometimes', 'boolean'],
            'file' => ['sometimes', 'file', 'mimes:pdf,epub', 'max:20480'],
        ]);
        $oldCover = $book->cover_path;
        $oldFile = $book->file_path;
        if (isset($data['title'])) {
            $book->title = trim($data['title']);
        }
        foreach (['author', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $book->{$field} = trim((string) $data[$field]);
            }
        }
        if (isset($data['isPublished'])) {
            $book->is_published = $data['isPublished'];
        }
        if ($request->file('cover')) {
            $book->cover_path = $request->file('cover')->store(Book::COVER_DIRECTORY, 'public');
        } elseif ($data['removeCover'] ?? false) {
            $book->cover_path = null;
        }
        if ($request->file('file')) {
            $book->file_path = $request->file('file')->store(Book::FILE_DIRECTORY, 'local');
            $book->file_name = mb_substr($request->file('file')->getClientOriginalName(), 0, 160);
        }
        $book->save();
        if ($oldCover && $oldCover !== $book->cover_path) {
            Storage::disk('public')->delete($oldCover);
        }
        if ($oldFile && $oldFile !== $book->file_path) {
            Storage::disk('local')->delete($oldFile);
        }
    }

    private static function all(int $status = 200): JsonResponse
    {
        return response()->json(['books' => Book::orderBy('position')->orderBy('id')->get()->map(fn (Book $book): array => MemberBookController::present($book, true, admin: true))], $status);
    }
}
