<?php
declare(strict_types=1);

class DatabaseRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(string $table, array $data): int {
        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ':' . $field, $fields);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int {
        $assignments = array_map(fn($field) => $field . ' = :' . $field, array_keys($data));
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $where;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $params));

        return $stmt->rowCount();
    }
}
