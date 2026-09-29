<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $checkCollections = [
        'prometheus_checks',
        'grafana_checks',
        'zabbix_checks',
        'elastic_checks',
        'victoria_logs_checks',
        'health_checks',
    ];

    /**
     * @var array<int, string>
     */
    private array $fireHistoryCollections = [
        'prometheus_histories',
        'elastic_histories',
        'victoria_logs_histories',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->index('apiToken');
            $table->index(['type', 'dataSourceIds']);
            $table->index('userId');
            $table->index('userIds');
            $table->index('teamIds');
            $table->index('watchUserIds');
            $table->index('tags');
            $table->index('skylogsInstanceId');
            $table->index('state');
        });

        Schema::table('alert_instances', function (Blueprint $table) {
            $table->index(['alertRuleId', 'instance']);
            $table->index(['alertRuleId', 'state', '_id']);
        });

        foreach ($this->checkCollections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->index(['alertRuleId', 'state']);
            });
        }

        Schema::table('health_checks', function (Blueprint $table) {
            $table->index('skylogsInstanceId');
        });

        Schema::table('zabbix_webhook_alerts', function (Blueprint $table) {
            $table->index(['alertRuleId', 'event_id']);
        });

        foreach ($this->fireHistoryCollections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->index(['alertRuleId', 'state', 'createdAt']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropIndex(['apiToken']);
            $table->dropIndex(['type', 'dataSourceIds']);
            $table->dropIndex(['userId']);
            $table->dropIndex(['userIds']);
            $table->dropIndex(['teamIds']);
            $table->dropIndex(['watchUserIds']);
            $table->dropIndex(['tags']);
            $table->dropIndex(['skylogsInstanceId']);
            $table->dropIndex(['state']);
        });

        Schema::table('alert_instances', function (Blueprint $table) {
            $table->dropIndex(['alertRuleId', 'instance']);
            $table->dropIndex(['alertRuleId', 'state', '_id']);
        });

        foreach ($this->checkCollections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->dropIndex(['alertRuleId', 'state']);
            });
        }

        Schema::table('health_checks', function (Blueprint $table) {
            $table->dropIndex(['skylogsInstanceId']);
        });

        Schema::table('zabbix_webhook_alerts', function (Blueprint $table) {
            $table->dropIndex(['alertRuleId', 'event_id']);
        });

        foreach ($this->fireHistoryCollections as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->dropIndex(['alertRuleId', 'state', 'createdAt']);
            });
        }
    }
};
