<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $collections = [
        'prometheus_histories',
        'elastic_histories',
        'victoria_logs_histories',
        'health_histories',
        'api_alert_status_histories',
        'zabbix_webhook_alerts',
        'grafana_webhook_alerts',
        'sentry_webhook_alerts',
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
