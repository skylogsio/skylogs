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
        Schema::create('runbooks', function (Blueprint $table) {
            $table->unique('slug');
            $table->index('name');
            $table->index('status');
            $table->index('teamIds');
            $table->index('tags');
            $table->index('appliesTo.serviceIds');
            $table->index('appliesTo.alertRuleIds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runbooks');
    }
};
