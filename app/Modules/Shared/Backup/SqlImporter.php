<?php

namespace App\Modules\Shared\Backup;

use PDO;
use RuntimeException;

/**
 * D-060: replays a DatabaseDumper file. Statements end with ";" at the end of a line; INSERT rows never span
 * lines because their newlines are escaped, so no SQL parser is needed.
 */
class SqlImporter
{
    /** Drops every base table in the connected database (used before an in-place restore). */
    public function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ((new DatabaseDumper)->tables($pdo) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public function import(PDO $pdo, string $path): int
    {
        $in = @fopen($path, 'rb');
        if ($in === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $statements = 0;
        $buffer = '';
        try {
            while (($line = fgets($in)) !== false) {
                if ($buffer === '' && (str_starts_with($line, '-- ') || trim($line) === '')) {
                    continue;
                }
                $buffer .= $line;
                if (str_ends_with(rtrim($line, "\r\n"), ';')) {
                    $pdo->exec($buffer);
                    $buffer = '';
                    $statements++;
                }
            }
            if (trim($buffer) !== '') {
                throw new RuntimeException('The database dump ends in the middle of a statement.');
            }
        } finally {
            fclose($in);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $statements;
    }
}
