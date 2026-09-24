<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->text('description');
            $table->string('status')->default('pending_review')->index();
            $table->string('budget_code')->nullable();
            $table->string('budget_description')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_folder_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
