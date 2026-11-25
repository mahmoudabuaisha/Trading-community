<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Other-language versions of text written by the administrators. A row
        // is valid only while `source_hash` still matches the original text, so
        // a translation never outlives an edit of what it translates.
        Schema::create('content_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translatable_type', 64);
            $table->unsignedBigInteger('translatable_id');
            $table->string('field', 32);
            $table->string('locale', 5);
            $table->text('text');
            $table->char('source_hash', 40);
            $table->timestamps();
            $table->unique(['translatable_type', 'translatable_id', 'field', 'locale'], 'content_translations_unique');
        });

        // Sections of the store added by the administrators ("Books", "Courses", …).
        Schema::create('store_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::table('store_items', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->constrained('store_categories')->nullOnDelete();
            // What a "product" hands over once bought: a link and/or a private file.
            $table->string('link', 500)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['link', 'file_path', 'file_name']);
        });
        Schema::dropIfExists('store_categories');
        Schema::dropIfExists('content_translations');
    }
};
