<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Collections SyncHaHistoryJob pages from the leader.
     *
     * @var array<int, string>
     */
    private array $collections = [
        'prometheus_histories',
        'grafana_webhook_alerts',
        'zabbix_webhook_alerts',
        'elastic_histories',
        'victoria_logs_histories',
        'health_histories',
        'api_alert_histories',
        'api_alert_status_histories',
        'sentry_webhook_alerts',
        'metabase_webhook_alerts',
        'notifies',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->collections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->index(['updatedAt', '_id']);
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
                $table->dropIndex(['updatedAt', '_id']);
            });
        }
    }
};
