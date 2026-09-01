<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // ponytail: one rule per project, columns rather than an alert_rules
            // table. Move to a table the day we want several rules or metrics.
            $table->unsignedTinyInteger('alert_error_rate_threshold')->nullable();
            $table->timestamp('alert_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['alert_error_rate_threshold', 'alert_notified_at']);
        });
    }
};
