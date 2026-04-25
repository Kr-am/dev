<?php

require_once __DIR__ . '/Database.php';

abstract class Model
{
    protected $db;
    protected $table;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all records
     */
    public function all()
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Find by ID
     */
    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    protected function findColumn(string $column, $value): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        $stmt = $this->db->prepare($sql);

        // Auto-detect type
        $type = is_int($value) ? 'i' : 's';
        $stmt->bind_param($type, $value);

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function countAll(): int
    {
        $result = $this->db->query("SELECT COUNT(*) as cnt FROM {$this->table}");
        $row = $result->fetch_assoc();
        return (int)$row['cnt'];
    }

    public function countBy(string $column, $value): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM {$this->table} WHERE {$column} = ?");
        $type = is_int($value) ? "i" : "s";
        $stmt->bind_param($type, $value);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return (int)$row['cnt'];
    }

    /**
     * Delete by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function where(string $column, $value): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $type = is_int($value) ? "i" : "s";
        $stmt->bind_param($type, $value);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    protected function insert(array $data): bool
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})"
        );

        $types = str_repeat('s', count($data));
        $values = array_values($data);

        // bind_param requires arguments by reference
        $bindParams = array_merge([$types], $values);
        $refs = array();
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);

        return $stmt->execute();
    }

    /**
     * Update data by ID
     */
    protected function update(int $id, array $data): bool
    {
        $set = implode(
            ', ',
            array_map(fn($key) => "{$key} = ?", array_keys($data))
        );

        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET {$set} WHERE id = ?"
        );

        $types = str_repeat('s', count($data)) . 'i';
        $values = array_values($data);
        $values[] = $id;

        // bind_param requires references
        $bindParams = array_merge([$types], $values);
        $refs = array();
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);

        return $stmt->execute();
    }


}