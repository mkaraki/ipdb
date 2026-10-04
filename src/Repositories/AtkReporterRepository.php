<?php

namespace Repositories;

class AtkReporterRepository
{
    public function __construct(
        private \mysqli $db
    ) {}

    public function count(): ?int
    {
        $res = $this->db->query('SELECT COUNT(*) FROM atk_reporter_users');
        if ($res === false) return null;
        return $res->fetch_row()[0];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `atk_reporter_users` WHERE `id` = ?');
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
        $stmt = $this->db->prepare('SELECT * FROM `atk_reporter_users` WHERE `username` = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row;
        } else { return null; }
    }

    public function updateById(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE `atk_reporter_users` SET
                                `username` = ?,
                                `password_hash` = ?,
                                `display_name` = ?
                            WHERE `id` = ?');
        $display_name = $data['display_name'] ?? null;
        $stmt->bind_param(
            'sssi',
            $data['username'],
            $data['password_hash'],
            $display_name,
            $id
        );
        return $stmt->execute();
    }

    public function store(array $data): ?int
    {
        $stmt = $this->db->prepare('INSERT INTO `atk_reporter_users` (`username`, `password_hash`, `display_name`) VALUES (?, ?, ?)');
        $display_name = $data['display_name'] ?? null;
        $stmt->bind_param('sss', $data['username'], $data['password_hash'], $display_name);

        if (!$stmt->execute()) {
            return null;
        }
        return $stmt->insert_id;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM `atk_reporter_users` WHERE `id` = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}