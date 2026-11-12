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
        Schema::create('ha_config_versions', function (Blueprint $table) {
            /*
             | Two rows at most, one per named counter, and the unique index is
             | what makes the leader's atomic bump safe under concurrent writes.
             */
            $table->string('name')->unique();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ha_config_versions');
    }
};
