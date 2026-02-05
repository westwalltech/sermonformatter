<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sermon_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entry_id')->index();
            $table->string('collection');
            $table->string('file_name');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->string('model')->nullable();
            $table->float('processing_time')->nullable(); // seconds
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sermon_processing_logs');
    }
};
