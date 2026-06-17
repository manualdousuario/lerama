<?php

declare(strict_types=1);

namespace Lerama\Services;

use DB;

class BulkInserter
{
    /**
     * Insert multiple rows in batches using a single prepared statement style query.
     *
     * @param string $table Target table name
     * @param array<int, array<string, mixed>> $rows Rows to insert; each row must have the same keys
     * @param array{ignore?: bool, batchSize?: int, onDuplicate?: string} $options
     *        - ignore: use INSERT IGNORE (default true)
     *        - batchSize: max rows per statement (default 100)
     *        - onDuplicate: optional ON DUPLICATE KEY UPDATE clause
     * @return int Number of rows successfully inserted (best effort when ignore=true)
     */
    public static function insert(string $table, array $rows, array $options = []): int
    {
        if (empty($rows)) {
            return 0;
        }

        $ignore = $options['ignore'] ?? true;
        $batchSize = max(1, min(1000, (int)($options['batchSize'] ?? 100)));
        $onDuplicate = $options['onDuplicate'] ?? '';

        $columns = array_keys(reset($rows));
        $columnList = '`' . implode('`, `', $columns) . '`';
        $placeholderTpl = '(' . implode(', ', array_fill(0, count($columns), '%s')) . ')';

        $inserted = 0;
        $batches = array_chunk($rows, $batchSize);

        foreach ($batches as $batch) {
            $placeholders = [];
            $params = [];

            foreach ($batch as $row) {
                $placeholders[] = $placeholderTpl;
                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    if ($value === null) {
                        $params[] = DB::sqleval('NULL');
                    } else {
                        $params[] = $value;
                    }
                }
            }

            $ignoreClause = $ignore ? 'IGNORE' : '';
            $duplicateClause = $onDuplicate ? ' ON DUPLICATE KEY UPDATE ' . $onDuplicate : '';
            $sql = sprintf(
                'INSERT %s INTO `%s` (%s) VALUES %s%s',
                $ignoreClause,
                $table,
                $columnList,
                implode(', ', $placeholders),
                $duplicateClause
            );

            try {
                DB::query($sql, ...$params);
                $inserted += count($batch);
            } catch (\Exception $e) {
                error_log("Bulk insert error on {$table}: " . $e->getMessage());
                throw $e;
            }
        }

        return $inserted;
    }
}
