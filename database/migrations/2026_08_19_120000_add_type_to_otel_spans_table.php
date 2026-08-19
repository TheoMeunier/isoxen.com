<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `type` categorizes a span for the project's sidebar (request,
     * outgoing_request, query, job, exception, ...). It's populated from the
     * `isoxen.type` span attribute -- a convention specific to isoxen's own
     * instrumentation client, not part of standard OTLP. Spans coming from a
     * generic/third-party OTEL SDK simply won't set it and end up with
     * `type = null`, which the UI treats as "uncategorized".
     */
    public function up(): void
    {
        Schema::table('otel_spans', function (Blueprint $table): void {
            $table->string('type', 32)->nullable()->after('name');

            $table->index(['project_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otel_spans', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
