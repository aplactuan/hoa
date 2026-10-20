<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Services\AuditLogger;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function created(Model $model): void
    {
        $this->auditLogger->log(
            AuditAction::Create,
            $model,
            [],
            $this->values($model, $model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->values($model, $model->getChanges());

        if ($changes === [] && ! $model->wasChanged('password')) {
            return;
        }

        $old = [];

        foreach (array_keys($changes) as $key) {
            $old[$key] = $this->scalar($model->getOriginal($key));
        }

        $summary = $changes === [] && $model->wasChanged('password')
            ? 'Password changed'
            : null;

        $this->auditLogger->log(AuditAction::Update, $model, $old, $changes, $summary);
    }

    public function deleted(Model $model): void
    {
        $this->auditLogger->log(
            AuditAction::Delete,
            $model,
            $this->values($model, $model->getOriginal()),
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function values(Model $model, array $attributes): array
    {
        $excluded = array_flip(method_exists($model, 'auditExcluded') ? $model->auditExcluded() : []);
        $clean = [];

        foreach ($attributes as $key => $value) {
            if (isset($excluded[$key])) {
                continue;
            }

            $clean[$key] = $this->scalar($value);
        }

        return $clean;
    }

    private function scalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return $value;
    }
}
