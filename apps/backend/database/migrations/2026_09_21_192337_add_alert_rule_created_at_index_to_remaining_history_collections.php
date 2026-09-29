<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * History collections queried by alertRuleId + createdAt that the 2026_07_02
     * index migration did not cover.
     *
     * @var array<int, string>
     */
    private array $collections = [
        'api_alert_histories',
        'metabase_webhook_alerts',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->collections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->index(['alertRuleId', 'createdAt']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->collections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->dropIndex(['alertRuleId', 'createdAt']);
            });
        }
    }
};
