<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (db_driver() === 'sqlite') {
        $path = (string) config('db.path', KW_ROOT . '/storage/app.sqlite');
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db.host', 'localhost'),
            (int) config('db.port', 3306),
            config('db.name', '')
        );
        $pdo = new PDO($dsn, (string) config('db.user', ''), (string) config('db.pass', ''), $options);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function db_driver(): string
{
    return config('db.driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

function db_run(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function db_insert(string $table, array $data): int
{
    $columns = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $columns),
        implode(', ', array_fill(0, count($columns), '?'))
    );
    db_run($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $params = []): int
{
    $sets = [];
    foreach (array_keys($data) as $column) {
        $sets[] = $column . ' = ?';
    }
    $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
    return db_run($sql, array_merge(array_values($data), $params))->rowCount();
}

/**
 * Monta o termo de uma busca com LIKE. Use junto com: LIKE ? ESCAPE '!'
 * O caractere de escape é "!" porque a barra invertida se comporta diferente no MySQL e no SQLite.
 */
function db_like(string $term): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
}

/** Lista de comandos que criam as tabelas. Funciona em MySQL e SQLite. */
function schema_statements(): array
{
    $sqlite = db_driver() === 'sqlite';
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $long = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
    $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        "CREATE TABLE IF NOT EXISTS companies (
            id $pk,
            name VARCHAR(160) NOT NULL,
            public_key CHAR(20) NOT NULL UNIQUE,
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            default_data $long NULL,
            default_updated_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )$suffix",
        "CREATE TABLE IF NOT EXISTS users (
            id $pk,
            company_id $fk NULL,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            last_login_at DATETIME NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )$suffix",
        "CREATE TABLE IF NOT EXISTS invites (
            id $pk,
            company_id $fk NULL,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            role VARCHAR(16) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            created_by $fk NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )$suffix",
        "CREATE TABLE IF NOT EXISTS signatures (
            id $pk,
            company_id $fk NOT NULL,
            name VARCHAR(160) NOT NULL,
            search_text VARCHAR(700) NOT NULL DEFAULT '',
            data $long NOT NULL,
            share_token CHAR(40) NOT NULL UNIQUE,
            created_by $fk NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )$suffix",
        "CREATE INDEX " . ($sqlite ? 'IF NOT EXISTS ' : '') . "idx_signatures_company ON signatures (company_id, name)",
        "CREATE TABLE IF NOT EXISTS assets (
            id $pk,
            company_id $fk NOT NULL,
            kind VARCHAR(16) NOT NULL,
            path VARCHAR(190) NOT NULL,
            path_circle VARCHAR(190) NULL,
            original_name VARCHAR(190) NOT NULL DEFAULT '',
            mime VARCHAR(40) NOT NULL,
            size_bytes INT NOT NULL DEFAULT 0,
            width INT NOT NULL DEFAULT 0,
            height INT NOT NULL DEFAULT 0,
            created_by $fk NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )$suffix",
        "CREATE TABLE IF NOT EXISTS password_resets (
            id $pk,
            user_id $fk NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )$suffix",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id $pk,
            attempt_key VARCHAR(190) NOT NULL,
            created_at INT NOT NULL
        )$suffix",
        "CREATE INDEX " . ($sqlite ? 'IF NOT EXISTS ' : '') . "idx_login_attempts ON login_attempts (attempt_key, created_at)",
    ];
}

/** Cria as tabelas que ainda não existem. Pode ser chamada mais de uma vez. */
function schema_install(): void
{
    foreach (schema_statements() as $sql) {
        try {
            db()->exec($sql);
        } catch (PDOException $e) {
            // No MySQL não existe CREATE INDEX IF NOT EXISTS: ignora o índice duplicado.
            $duplicateIndex = stripos($sql, 'CREATE INDEX') === 0
                && (int) ($e->errorInfo[1] ?? 0) === 1061;
            if (!$duplicateIndex) {
                throw $e;
            }
        }
    }
}

function schema_installed(): bool
{
    try {
        db_value('SELECT COUNT(*) FROM users');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
