<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Schema\Blueprint;
use Throwable;

/**
 * Create or drop MongoDB indexes without failing when they are already present
 * (or already gone on rollback).
 */
final class MongoIndexes
{
    /**
     * IndexOptionsConflict / IndexKeySpecsConflict: the keys (or name) already exist.
     *
     * @var array<int, int>
     */
    private const ALREADY_EXISTS_CODES = [85, 86];

    /**
     * @param  string|array<int, string>  $columns
     */
    public static function ensure(Blueprint $table, string|array $columns, bool $unique = false): void
    {
        $columns = is_array($columns) ? array_values($columns) : [$columns];

        if (self::exists($table, $columns)) {
            return;
        }

        try {
            if ($unique) {
                $table->unique($columns);
            } else {
                $table->index($columns);
            }
        } catch (Throwable $exception) {
            if (self::isAlreadyExists($exception)) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * @param  string|array<int, string>  $columns
     */
    public static function dropIfExists(Blueprint $table, string|array $columns): void
    {
        $columns = is_array($columns) ? array_values($columns) : [$columns];

        try {
            $table->dropIndexIfExists($columns);
        } catch (Throwable) {
            // Collection or index is already gone.
        }
    }

    public static function isAlreadyExists(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if (in_array($current->getCode(), self::ALREADY_EXISTS_CODES, true)) {
                return true;
            }

            $message = strtolower($current->getMessage());

            if (
                str_contains($message, 'already exists')
                || str_contains($message, 'indexoptionsconflict')
                || str_contains($message, 'indexkeyspecsconflict')
            ) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private static function exists(Blueprint $table, array $columns): bool
    {
        try {
            if ($table->hasIndex($columns)) {
                return true;
            }

            $expected = [];

            foreach ($columns as $column) {
                $expected[$column] = 1;
            }

            $indexes = DB::connection()->getCollection($table->getTable())->listIndexes();

            foreach ($indexes as $index) {
                $key = [];

                foreach ($index->getKey() as $field => $direction) {
                    $key[$field] = (int) $direction;
                }

                if ($key === $expected) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
