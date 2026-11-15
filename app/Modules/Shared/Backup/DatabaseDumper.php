<?php

namespace App\Modules\Shared\Backup;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * D-060: writes a MySQL/MariaDB dump in plain SQL with PHP only (no mysqldump or shell access needed on shared
 * hosting). The file can also be imported by hand with phpMyAdmin. Each INSERT is one line: PDO::quote() escapes
 * newlines, so SqlImporter can split statements safely. Rows are read inside one consistent snapshot.
 */
final class DatabaseDumper
{
    private const BATCH_BYTES = 524288;

    /**
     * @param  list<string>  $structureOnly  tables whose rows are skipped
     * @return array<string, int> rows written per table
     */
    public function dump(Connection $connection, string $path, array $structureOnly = []): array
    {
        $pdo = $connection->getPdo();
        $out = @fopen($path, 'wb');
        if ($out === false) {
            throw new RuntimeException("Cannot write {$path}.");
        }

        // A consistent snapshot of every table; skipped when a caller (such as a test) already holds a transaction.
        $snapshot = $connection->transactionLevel() === 0;
        if ($snapshot) {
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }

        $counts = [];
        try {
            fwrite($out, "-- JCA Student Management System database backup\n-- Created ".now()->toIso8601String()."\n");
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");

            foreach ($this->tables($pdo) as $table) {
                $create = $pdo->query('SHOW CREATE TABLE '.$this->name($table))->fetch(PDO::FETCH_NUM)[1];
                fwrite($out, "\n-- Table {$table}\nDROP TABLE IF EXISTS ".$this->name($table).";\n{$create};\n");
                $counts[$table] = in_array($table, $structureOnly, true) ? 0 : $this->rows($pdo, $table, $out);
            }

            fwrite($out, "\nSET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n-- End of backup\n");
        } finally {
            if ($snapshot) {
                $pdo->exec('COMMIT');
            }
            fclose($out);
        }

        return $counts;
    }

    /** @return list<string> */
    public function tables(PDO $pdo): array
    {
        return $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /** @param resource $out */
    private function rows(PDO $pdo, string $table, $out): int
    {
        $binary = [];
        foreach ($pdo->query('SHOW COLUMNS FROM '.$this->name($table))->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $binary[$column['Field']] = (bool) preg_match('/^(tiny|medium|long)?blob|^(var)?binary/i', $column['Type']);
        }

        $statement = $pdo->query('SELECT * FROM '.$this->name($table));
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $prefix = null;
        $batch = [];
        $bytes = 0;
        $count = 0;
        foreach ($statement as $row) {
            $prefix ??= 'INSERT INTO '.$this->name($table).' ('.implode(',', array_map($this->name(...), array_keys($row))).') VALUES ';
            $values = [];
            foreach ($row as $column => $value) {
                $values[] = match (true) {
                    $value === null => 'NULL',
                    $binary[$column] ?? false => "X'".bin2hex((string) $value)."'",
                    default => $pdo->quote((string) $value),
                };
            }
            $tuple = '('.implode(',', $values).')';
            $batch[] = $tuple;
            $bytes += strlen($tuple);
            $count++;
            if ($bytes >= self::BATCH_BYTES) {
                fwrite($out, $prefix.implode(',', $batch).";\n");
                $batch = [];
                $bytes = 0;
            }
        }
        if ($batch !== []) {
            fwrite($out, $prefix.implode(',', $batch).";\n");
        }

        return $count;
    }

    private function name(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
