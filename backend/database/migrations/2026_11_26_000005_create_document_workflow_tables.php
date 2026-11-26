<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->string('letter_number')->nullable()->after('workflow_type');
            $table->date('letter_date')->nullable()->after('letter_number');
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('workflow_scope', 32)->default('shared');
            $table->string('file_path');
            $table->json('placeholders_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workflow_scope', 'is_active'], 'document_templates_scope_active_idx');
        });

        Schema::create('borrow_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->unique()->constrained('borrow_requests')->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->unsignedInteger('current_revision')->default(1);
            $table->string('document_status', 64)->default('draft');
            $table->string('final_pdf_path')->nullable();
            $table->string('final_pdf_sha256', 64)->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('document_status', 'borrow_documents_status_idx');
        });

        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_document_id')->constrained('borrow_documents')->cascadeOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->string('signer_role', 32); // requester_eskul | mpk | owner_eskul
            $table->unsignedTinyInteger('stage_order'); // 1, 2, 3
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name_snapshot');
            $table->string('signer_title_snapshot');
            $table->string('signer_id_number_snapshot')->nullable();
            $table->string('signature_image_path_snapshot');
            $table->boolean('is_valid')->default(true);
            $table->timestamp('signed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['borrow_document_id', 'revision_number', 'stage_order'], 'doc_sigs_doc_rev_stage_idx');
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_document_id')->constrained('borrow_documents')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('snapshot_json');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['borrow_document_id', 'revision_number'], 'doc_revs_doc_rev_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('document_signatures');
        Schema::dropIfExists('borrow_documents');
        Schema::dropIfExists('document_templates');

        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropColumn(['letter_number', 'letter_date']);
        });
    }
};

