<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained('borrow_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32);
            $table->string('action', 64);
            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64);
            $table->text('note')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['borrow_request_id', 'created_at'], 'approval_histories_req_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
    }
};

