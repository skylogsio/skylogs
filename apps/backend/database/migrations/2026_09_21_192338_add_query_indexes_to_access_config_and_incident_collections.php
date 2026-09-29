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
        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->unique('name');
            $table->index('ownerId');
            $table->index('userIds');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->index(['userId', 'onCall']);
            $table->index('accessUserIds');
            $table->index('accessTeamIds');
            $table->index('type');
        });

        Schema::table('endpoint_otp', function (Blueprint $table) {
            $table->index(['type', 'value']);
        });

        Schema::table('data_sources', function (Blueprint $table) {
            $table->index('webhookToken');
            $table->index('type');
        });

        Schema::table('skylogs_instances', function (Blueprint $table) {
            $table->index('token');
        });

        Schema::table('ha_history_sync_cursors', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('ha_state_versions', function (Blueprint $table) {
            $table->index(['state', 'updatedAt']);
        });

        Schema::table('config_skylogs', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('config_emails', function (Blueprint $table) {
            $table->index('isDefault');
        });

        Schema::table('config_sms', function (Blueprint $table) {
            $table->index('isDefault');
        });

        Schema::table('config_calls', function (Blueprint $table) {
            $table->index('isDefault');
        });

        Schema::table('config_telegrams', function (Blueprint $table) {
            $table->index('active');
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->index('alertRuleIds');
            $table->index(['policyId', 'groupingKey', 'source', 'status', 'detectedAt']);
            $table->index('createdBy');
            $table->index('severity');
            $table->index('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['username']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['ownerId']);
            $table->dropIndex(['userIds']);
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropIndex(['userId', 'onCall']);
            $table->dropIndex(['accessUserIds']);
            $table->dropIndex(['accessTeamIds']);
            $table->dropIndex(['type']);
        });

        Schema::table('endpoint_otp', function (Blueprint $table) {
            $table->dropIndex(['type', 'value']);
        });

        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropIndex(['webhookToken']);
            $table->dropIndex(['type']);
        });

        Schema::table('skylogs_instances', function (Blueprint $table) {
            $table->dropIndex(['token']);
        });

        Schema::table('ha_history_sync_cursors', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('ha_state_versions', function (Blueprint $table) {
            $table->dropIndex(['state', 'updatedAt']);
        });

        Schema::table('config_skylogs', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('config_emails', function (Blueprint $table) {
            $table->dropIndex(['isDefault']);
        });

        Schema::table('config_sms', function (Blueprint $table) {
            $table->dropIndex(['isDefault']);
        });

        Schema::table('config_calls', function (Blueprint $table) {
            $table->dropIndex(['isDefault']);
        });

        Schema::table('config_telegrams', function (Blueprint $table) {
            $table->dropIndex(['active']);
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['alertRuleIds']);
            $table->dropIndex(['policyId', 'groupingKey', 'source', 'status', 'detectedAt']);
            $table->dropIndex(['createdBy']);
            $table->dropIndex(['severity']);
            $table->dropIndex(['tags']);
        });
    }
};
