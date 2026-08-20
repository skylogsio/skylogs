<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_mortems', function (Blueprint $table) {
            $table->unique('incidentId');
            $table->index('status');
            $table->index('authorId');
            $table->index('dueAt');
        });

        Schema::create('incident_timeline_entries', function (Blueprint $table) {
            $table->index(['incidentId', 'occurredAt']);
            $table->index('type');
            $table->index('userId');
        });

        Schema::create('incident_documents', function (Blueprint $table) {
            $table->index('incidentId');
            $table->index(['attachableType', 'attachableId']);
            $table->index('uploadedBy');
        });

        Schema::create('incident_action_items', function (Blueprint $table) {
            $table->index('incidentId');
            $table->index('postMortemId');
            $table->index(['ownerId', 'status']);
            $table->index('teamId');
            $table->index('dueAt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_action_items');
        Schema::dropIfExists('incident_documents');
        Schema::dropIfExists('incident_timeline_entries');
        Schema::dropIfExists('post_mortems');
    }
};
