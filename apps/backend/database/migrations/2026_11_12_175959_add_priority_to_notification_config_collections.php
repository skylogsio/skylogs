<?php

use App\Models\Config\ConfigCall;
use App\Models\Config\ConfigSms;
use App\Models\Config\ConfigTelegram;
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
        foreach (['config_sms', 'config_calls', 'config_telegrams'] as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->index('priority');
            });
        }

        ConfigSms::query()->cursor()->each(function (ConfigSms $config): void {
            $config->update(['priority' => $this->smsOrCallPriority($config->isDefault, $config->isBackUp)]);
        });

        ConfigCall::query()->cursor()->each(function (ConfigCall $config): void {
            $config->update(['priority' => $this->smsOrCallPriority($config->isDefault, $config->isBackUp)]);
        });

        ConfigTelegram::query()->cursor()->each(function (ConfigTelegram $config): void {
            $config->update(['priority' => $config->active ? 1 : null]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['config_sms', 'config_calls', 'config_telegrams'] as $collection) {
            Schema::table($collection, function (Blueprint $table) {
                $table->dropIndex(['priority']);
            });
        }

        foreach ([ConfigSms::class, ConfigCall::class, ConfigTelegram::class] as $model) {
            $model::query()->update(['priority' => null]);
        }
    }

    private function smsOrCallPriority(mixed $isDefault, mixed $isBackUp): ?int
    {
        if ($isDefault) {
            return 1;
        }

        if ($isBackUp) {
            return 2;
        }

        return null;
    }
};
