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
        Schema::create('incident_policies', function (Blueprint $table) {
            $table->unique('name');
            $table->index('enabled');
            $table->index('teamIds');
            $table->index('match.alertRuleIds');
            $table->index('match.tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_policies');
    }
};
