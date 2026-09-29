<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-domain import success/failure instrumentation (architecture doc
     * Rev 2 addition, §3). One row per import attempt so we can answer
     * "which domains fail JSON-LD parsing" without re-deriving it from logs —
     * this is the data point that will tell us whether to revisit paid
     * parser vendors later.
     */
    public function up(): void
    {
        Schema::create('recipe_import_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url', 2048);
            $table->string('domain')->index();
            $table->string('outcome'); // json_ld_success | json_ld_absent | json_ld_invalid | fetch_failed
            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('ingredient_lines_found')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['domain', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_import_events');
    }
};
