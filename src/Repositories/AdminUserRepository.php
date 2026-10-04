<?php

namespace Repositories;

class AdminUserRepository
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function count(): ?int
    {
        $res = $this->db->query('SELECT COUNT(*) FROM admin_users');
        if ($res === false) return null;
        return $res->fetch_row()[0];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `admin_users` WHERE `id` = ?');
        $stmt->bind_param('i', $id);

        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row;
        } else {
            return null;
        }
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `admin_users` WHERE `username` = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row;
        } else { return null; }
    }

    public function updateById(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE `admin_users` SET `username` = ?, `password_hash` = ? WHERE `id` = ?');
        $stmt->bind_param('ssi', $data['username'], $data['password_hash'], $id);
        return $stmt->execute();
    }

    public function store(array $data): ?int
    {
        $stmt = $this->db->prepare('INSERT INTO `admin_users` (`username`, `password_hash`) VALUES (?, ?)');
        $stmt->bind_param('ss', $data['username'], $data['password_hash']);

        if (!$stmt->execute()) {
            return null;
        }
        return $stmt->insert_id;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM `admin_users` WHERE `id` = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}