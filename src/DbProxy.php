<?php

const SCHEMA_VERSION = 202605160001;

function db_init() {
    $mysqli = new mysqli(
        DB_HOSTNAME,
        DB_USERNAME,
        DB_PASSWORD,
        DB_DATABASE,
    );

    return $mysqli;
}

function db_close(mysqli $mysqli): bool {
    return $mysqli->close();
}

function check_schema_version(mysqli $mysqli): bool {
    $result = query_row_params($mysqli, 'SELECT version FROM db_schema_version', '', []);
    if ($result === false) {
        throw new RuntimeException('Failed to retrieve schema version');
        return false;
    }

    if ($result === null) {
        return false;
    }

    return $result['version'] === SCHEMA_VERSION;
}

// Returns:
//  - false: On error
//  - null: No rows
//  - array: Row data
function query_row_params(mysqli $db, string $sql, string $types = '', array $params = []): array|false|null {
    $result = query_result_params($db, $sql, $types, $params);
    if ($result === false) {
        return false;
    }

    return $result->fetch_assoc();
}

function query_all_params(mysqli $db, string $sql, string $types = '', array $params = []): array|false {
    $result = query_result_params($db, $sql, $types, $params);
    if ($result === false) {
        return false;
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function query_result_params(mysqli $db, string $sql, string $types = '', array $params = []): false|mysqli_result {
    $parent = \Sentry\SentrySdk::getCurrentHub()->getSpan();
    $span = null;
    if ($parent !== null) {
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('db.query')
            ->setDescription($sql)
            ->setData([
                'db.system' => 'mariadb'
            ]);
        $span = $parent->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);
    }

    try {
        $stmt = $db->prepare($sql);
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            return false;
        }
        return $stmt->get_result();
    } finally {
        if ($span !== null) {
            $span->finish();
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
        }
    }
}

function query_params(mysqli $db, string $sql, string $types = '', array $params = []): bool {
    $parent = \Sentry\SentrySdk::getCurrentHub()->getSpan();
    $span = null;
    if ($parent !== null) {
        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp('db.query')
            ->setDescription($sql)
            ->setData([
                'db.system' => 'mariadb'
            ]);
        $span = $parent->startChild($context);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($span);
    }

    try {
        $stmt = $db->prepare($sql);
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        return $stmt->execute();
    } finally {
        if ($span !== null) {
            $span->finish();
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
        }
    }
}