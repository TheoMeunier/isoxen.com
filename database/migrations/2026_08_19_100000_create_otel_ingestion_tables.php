<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These three tables store raw OTEL ingestion data as plain Postgres
     * tables for now. TimescaleDB (hypertables partitioned by `time`) was
     * initially planned per ADR-0001 but has been deferred -- see the
     * amendment at the bottom of that ADR. The `time` column and the
     * `(project_id, time)` index are kept as-is so that switching to a
     * hypertable later doesn't require reshaping the schema, only adding
     * the extension and running `create_hypertable(...)`.
     */
    public function up(): void
    {
        $this->createSpansTable();
        $this->createMetricsTable();
        $this->createLogsTable();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otel_logs');
        Schema::dropIfExists('otel_metrics');
        Schema::dropIfExists('otel_spans');
    }

    private function createSpansTable(): void
    {
        Schema::create('otel_spans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('trace_id', 32)->nullable();
            $table->string('span_id', 16)->nullable();
            $table->string('parent_span_id', 16)->nullable();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('kind')->nullable();
            $table->timestampTz('time');
            $table->timestampTz('end_time')->nullable();
            $table->unsignedBigInteger('duration_nanos')->nullable();
            $table->unsignedTinyInteger('status_code')->nullable();
            $table->text('status_message')->nullable();
            $table->json('resource_attributes')->nullable();
            $table->json('attributes')->nullable();
            $table->json('raw')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['project_id', 'time']);
            $table->index('trace_id');
        });
    }

    private function createMetricsTable(): void
    {
        Schema::create('otel_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('unit')->nullable();
            $table->string('type', 32)->nullable();
            $table->timestampTz('time');
            $table->double('value')->nullable();
            $table->json('resource_attributes')->nullable();
            $table->json('attributes')->nullable();
            $table->json('raw')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['project_id', 'time']);
            $table->index(['project_id', 'name']);
        });
    }

    private function createLogsTable(): void
    {
        Schema::create('otel_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('trace_id', 32)->nullable();
            $table->string('span_id', 16)->nullable();
            $table->unsignedSmallInteger('severity_number')->nullable();
            $table->string('severity_text')->nullable();
            $table->text('body')->nullable();
            $table->timestampTz('time');
            $table->json('resource_attributes')->nullable();
            $table->json('attributes')->nullable();
            $table->json('raw')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['project_id', 'time']);
            $table->index('trace_id');
        });
    }
};
