<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('query_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 64);
            $table->text('query_text');
            $table->string('model', 100);
            $table->integer('dimensions');
            $table->integer('use_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['query_hash', 'model', 'dimensions'], 'query_embeddings_hash_model_dims_unique');
            $table->index('last_used_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE query_embeddings ADD COLUMN embedding_vector vector(1536)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_embeddings');
    }
};
