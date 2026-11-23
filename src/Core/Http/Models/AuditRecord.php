<?php

namespace Sonole\GovGr\Core\Http\Models;

use Sonole\GovGr\Core\Enums\GovService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * @property int $id
 * @property Carbon $transaction_date
 * @property string|null $protocol
 * @property GovService|null $service
 * @property string|null $environment
 * @property string|null $endpoint
 * @property string $user_email
 * @property string|null $user_ip
 * @property int|null $user_id
 * @property int|null $status_code
 * @property string|null $exception
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AuditRecord extends Model
{
    protected $guarded = ['id'];

    private static ?bool $tableExists = null;

    public function getTable(): string
    {
        return config('govgr.audit.table', 'audit_records');
    }

    /**
     * Creates a row for one physical call and stamps its own protocol
     * ("{id}/{d-m-Y}") from its own id, the format every audited call uses.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws RuntimeException
     */
    public static function record(array $attributes): self
    {
        self::ensureTableExists();

        $record = static::query()->create($attributes);

        $record->update(['protocol' => $record->id.'/'.$record->transaction_date->format('d-m-Y')]);

        return $record;
    }

    /** @throws RuntimeException */
    private static function ensureTableExists(): void
    {
        if (self::$tableExists === true) {
            return;
        }

        $table = (new self)->getTable();

        if (! Schema::hasTable($table)) {
            throw new RuntimeException(
                "The {$table} table does not exist. Run \"php artisan migrate\" to create it before using gov package services."
            );
        }

        self::$tableExists = true;
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'service' => GovService::class,
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'user_id');
    }

    /** @return class-string<Model> */
    private function userModelClass(): string
    {
        $model = config('govgr.audit.system_user_model') ?? config('auth.providers.users.model', 'App\\Models\\User');

        return is_string($model) && is_a($model, Model::class, true) ? $model : Model::class;
    }
}
