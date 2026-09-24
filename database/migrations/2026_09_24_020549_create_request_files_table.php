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
        Schema::create('request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('type'); // invoice | proof_transfer | official_receipt
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('drive_file_id')->nullable();
            $table->string('drive_url')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_files');
    }
};
