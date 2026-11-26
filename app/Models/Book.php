<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A paid book: listed for everyone, readable only by members it was opened for. */
class Book extends Model
{
    use Translatable;

    public const TRANSLATABLE = ['title', 'description'];

    public const COVER_DIRECTORY = 'books';

    public const FILE_DIRECTORY = 'book-files';

    protected $fillable = ['title', 'author', 'description', 'position', 'is_published'];

    protected $attributes = ['author' => '', 'description' => '', 'is_published' => true];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (Book $book): void {
            if ($book->cover_path) {
                Storage::disk('public')->delete($book->cover_path);
            }
            if ($book->file_path) {
                Storage::disk('local')->delete($book->file_path);
            }
            SubscriptionGrant::forget('book', $book->id);
        });
    }
}
