<?php

namespace Sonole\GovGr\Core\Console\Commands;

use DateTime;
use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AuditReportCommand extends Command
{
    protected $signature = 'govgr:audit-report
        {--service= : Filter by service, e.g. gemi, amka_info, amka_relationship, ergani}
        {--from= : Only include calls on or after this date, e.g. 2026-08-01}
        {--limit=50}
        {--format=table : table|csv}';

    protected $description = 'Export audit records, e.g. as proof of test calls for a production credential request.';

    public function handle(): int
    {
        $table = (new AuditRecord)->getTable();

        if (! Schema::hasTable($table)) {
            $this->error("The {$table} table does not exist. Run \"php artisan migrate\" to create it.");

            return self::FAILURE;
        }

        $query = AuditRecord::query()->latest('transaction_date');

        if ($service = $this->option('service')) {
            if (! is_string($service) || GovService::tryFrom($service) === null) {
                $valid = implode(', ', array_column(GovService::cases(), 'value'));
                $this->error("The --service option must be one of: $valid.");

                return self::FAILURE;
            }

            $query->where('service', $service);
        }

        if ($from = $this->option('from')) {
            if (! is_string($from) || ! self::isValidDate($from)) {
                $this->error('The --from option must be a date in YYYY-MM-DD format, e.g. 2026-08-01.');

                return self::FAILURE;
            }

            $query->where('transaction_date', '>=', $from);
        }

        $limit = filter_var(
            $this->option('limit'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($limit === false) {
            $this->error('The --limit option must be a positive integer.');

            return self::FAILURE;
        }

        $format = $this->option('format');

        if (! in_array($format, ['table', 'csv'], true)) {
            $this->error('The --format option must be one of: table, csv.');

            return self::FAILURE;
        }

        $records = $query->limit($limit)->get([
            'id', 'protocol', 'service', 'environment', 'endpoint', 'status_code', 'transaction_date', 'user_email',
        ]);

        if ($format === 'csv') {
            $this->line('id,protocol,service,environment,endpoint,status_code,transaction_date,user_email');

            foreach ($records as $record) {
                $this->line(implode(',', [
                    $record->id,
                    $record->protocol,
                    $record->service?->value,
                    $record->environment,
                    $record->endpoint,
                    $record->status_code,
                    $record->transaction_date->toDateTimeString(),
                    $record->user_email,
                ]));
            }

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Protocol', 'Service', 'Environment', 'Endpoint', 'Status', 'Date', 'User'],
            $records->map(fn ($record) => [
                $record->id,
                $record->protocol,
                $record->service?->value,
                $record->environment,
                $record->endpoint,
                $record->status_code ?? 'NULL',
                $record->transaction_date->toDateTimeString(),
                $record->user_email,
            ])
        );

        return self::SUCCESS;
    }

    /** Rejects malformed strings and impossible calendar dates (e.g. 2026-02-31) via a format round-trip. */
    private static function isValidDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
