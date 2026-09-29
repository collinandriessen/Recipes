<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_url')->nullable();
            $table->string('source_type')->default('manual'); // url_import | manual
            $table->unsignedInteger('servings')->default(1);
            $table->string('photo_path')->nullable();
            $table->json('instructions')->nullable();
            $table->string('import_status')->default('manual'); // pending | parsed | needs_review | manual
            $table->timestamps();

            $table->index(['user_id', 'import_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
