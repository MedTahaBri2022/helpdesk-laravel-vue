<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->index();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('medium');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // The customer who opened the ticket.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The agent in charge; the ticket survives if the agent leaves.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // "My tickets", newest first.
            $table->index(['user_id', 'created_at']);
            // The queue of the agents: filter by status, then by assignee.
            $table->index(['status', 'assigned_to']);
            $table->index('priority');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // Internal notes are visible to staff only.
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('categories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
