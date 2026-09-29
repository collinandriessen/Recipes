<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_allergens', function (Blueprint $table) {
            // fdc_category | keyword_rule | manual_curation — per architecture §2.
            // Nullable/backfilled default for any rows created before this
            // column existed; new rows always set it explicitly.
            $table->string('mapping_source')->default('keyword_rule')->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_allergens', function (Blueprint $table) {
            $table->dropColumn('mapping_source');
        });
    }
};
