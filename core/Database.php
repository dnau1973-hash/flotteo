<?php
declare(strict_types=1);

namespace Core;

/**
 * Singleton PDO : connexion centralisée, requêtes préparées systématiques.
 */
final class Database
{
    private static ?\PDO $instance = null;

    public static function pdo(): \PDO
    {
        if (self::$instance instanceof \PDO) {
            return self::$instance;
        }

        $conf = require dirname(__DIR__) . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $conf['host'],
            (int) $conf['port'],
            $conf['database'],
            $conf['charset']
        );

        try {
            self::$instance = new \PDO($dsn, $conf['username'], $conf['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            Logger::error('Connexion BDD impossible', $e);
            http_response_code(500);
            exit('Service temporairement indisponible. Contactez l\'administrateur.');
        }

        return self::$instance;
    }

    /** Exécute une requête préparée et retourne le statement. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Retourne toutes les lignes. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Retourne la première ligne ou null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Retourne la première colonne scalaire de la première ligne. */
    public static function scalar(string $sql, array $params = [], mixed $defaut = null): mixed
    {
        $val = self::run($sql, $params)->fetchColumn();
        return $val === false ? $defaut : $val;
    }

    /** Insère une ligne et retourne l'identifiant généré. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** Met à jour une ligne par identifiant. */
    public static function update(string $table, int $id, array $data): int
    {
        $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($data)));
        $params = $data;
        $params['id'] = $id;
        return self::run("UPDATE $table SET $sets WHERE id = :id", $params)->rowCount();
    }

    /** Supprime une ligne par identifiant. */
    public static function delete(string $table, int $id): int
    {
        return self::run("DELETE FROM $table WHERE id = :id", ['id' => $id])->rowCount();
    }
}
