<?php

/**
 * db_smoke_test.php
 * Purpose: CLI smoke test to validate schema, seed data, queries, and stored procedures.
 * Usage: DB_USER=... DB_PASSWORD=... php db_smoke_test.php
 */

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

function env(string $key, ?string $default = null): ?string {
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

function fail(string $message, int $exitCode = 1): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit($exitCode);
}

function ok(string $message): void {
    fwrite(STDOUT, "OK: {$message}\n");
}

function ensure(bool $condition, string $message): void {
    if (!$condition) {
        fail($message);
    }
}

function moneyToFloat(string $value): float {
    $normalized = str_replace([',', '$'], '', $value);
    return (float)$normalized;
}

function validateQueryRequirements(string $type, array $rows, array $post): void {
    if ($type === '1') {
        ensure(count($rows) > 0, 'Query 1 returned no rows.');
        foreach ($rows as $row) {
            ensure(($row['client_type'] ?? '') === 'Business', 'Query 1 returned non-business client.');
        }
        return;
    }

    if ($type === '2') {
        ensure(count($rows) > 0, 'Query 2 returned no rows.');
        foreach ($rows as $row) {
            ensure(((int)($row['res_id'] ?? 0)) > 1, 'Query 2 returned reservation with res_id <= 1.');
        }
        return;
    }

    if ($type === '3') {
        $groupBy = $post['query3_group_by'] ?? 'none';
        ensure(count($rows) > 0, 'Query 3 returned no rows.');
        if ($groupBy === 'none') {
            foreach ($rows as $row) {
                ensure(isset($row['driver_id']) && isset($row['vehicle_id']), 'Query 3 (none) missing driver/vehicle linkage.');
            }
        }
        return;
    }

    if ($type === '4') {
        $groupBy = $post['query4_group_by'] ?? 'none';
        ensure(count($rows) > 0, 'Query 4 returned no rows.');
        if ($groupBy === 'none') {
            foreach ($rows as $row) {
                $date = $row['appointment_date'] ?? '';
                ensure($date >= '2026-03-11' && $date <= '2026-03-18', 'Query 4 returned mission outside Mar 11-18 range.');
                $status = $row['mission_status'] ?? '';
                ensure(in_array($status, ['Scheduled', 'Completed'], true), 'Query 4 returned unexpected mission status label.');
            }
        }
        return;
    }

    if ($type === '5') {
        ensure(count($rows) > 0, 'Query 5 returned no rows.');
        foreach ($rows as $row) {
            ensure(($row['pay_status'] ?? '') === 'Pending', 'Query 5 returned a paid invoice row.');
        }
        return;
    }

    if ($type === '6') {
        ensure(count($rows) > 0, 'Query 6 returned no rows.');
        foreach ($rows as $row) {
            ensure(($row['vehicle_brand'] ?? '') === 'GMC', 'Query 6 returned non-GMC vehicle.');
        }
        return;
    }

    if ($type === '7') {
        ensure(count($rows) > 0, 'Query 7 returned no rows.');
        foreach ($rows as $row) {
            $total = moneyToFloat((string)($row['total_rental_cost'] ?? '0'));
            ensure($total > 1000.0, 'Query 7 returned invoice total <= 1000.');
        }
        return;
    }

    if ($type === '8') {
        ensure(count($rows) > 0, 'Query 8 returned no rows.');
        foreach ($rows as $row) {
            ensure(((int)($row['invoice_count'] ?? -1)) >= 0, 'Query 8 returned invalid invoice_count.');
        }
        return;
    }

    if ($type === '9') {
        ensure(count($rows) > 0, 'Query 9 returned no rows.');
        foreach ($rows as $row) {
            ensure(((int)($row['max_kilometers_traveled'] ?? 0)) > 7000, 'Query 9 returned mileage <= 7000.');
        }
    }
}

function clearAllResults(mysqli $conn): void {
    while ($conn->more_results()) {
        $conn->next_result();
        $res = $conn->store_result();
        if ($res instanceof mysqli_result) {
            $res->free();
        }
    }
}

$host = env('DB_HOST', 'qwc353.encs.concordia.ca');
$dbName = env('DB_NAME', 'qwc353_4');
$user = env('DB_USER');
$pass = env('DB_PASSWORD');

if ($user === null || $pass === null) {
    fail('Missing DB_USER or DB_PASSWORD environment variables.');
}

$conn = new mysqli($host, $user, $pass, $dbName);
if ($conn->connect_error) {
    fail('Unable to connect: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
ok("Connected to {$host}/{$dbName} as {$user}");

$requiredTables = [
    'CLIENT',
    'DRIVER',
    'VEHICLE',
    'VEHICLE_RATE',
    'RESERVATION',
    'MISSION',
    'INVOICE',
    'INVOICE_LINE',
    'PAYMENT',
];

$requiredColumns = [
    'CLIENT' => ['client_id', 'client_name', 'client_addr', 'client_phone', 'client_type'],
    'DRIVER' => ['driver_id', 'driver_licence_type', 'driver_first_name', 'driver_last_name'],
    'VEHICLE' => ['vehicle_id', 'vehicle_type', 'vehicle_brand'],
    'VEHICLE_RATE' => ['vehicle_type', 'price_per_day', 'price_per_km'],
    'RESERVATION' => ['res_id', 'client_id', 'reservation_date', 'res_status', 'requested_vehicle_type', 'expected_duration', 'rendezvous_location', 'appointment_datetime'],
    // expected_duration is derived via RESERVATION; MISSION stores only the mutable/billable duration.
    'MISSION' => ['mission_id', 'res_id', 'driver_id', 'vehicle_id', 'rendezvous_location', 'appointment_datetime', 'duration', 'actual_start_datetime', 'actual_end_datetime', 'odometer_start', 'odometer_end', 'mission_status'],
    'INVOICE' => ['invoice_id', 'client_id', 'invoice_date'],
    'INVOICE_LINE' => ['invoice_id', 'mission_id', 'duration', 'rental_cost'],
    'PAYMENT' => ['payment_id', 'invoice_id', 'method', 'pay_status', 'amount', 'pay_date'],
];

$minRows = [
    'CLIENT' => 10,
    'DRIVER' => 10,
    'VEHICLE' => 10,
    'INVOICE' => 10,
    'VEHICLE_RATE' => 3,
    'RESERVATION' => 5,
    'MISSION' => 5,
    'INVOICE_LINE' => 5,
    'PAYMENT' => 5,
];

foreach ($requiredTables as $table) {
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
    );
    if (!$stmt) {
        fail('Unable to prepare table check: ' . $conn->error);
    }
    $stmt->bind_param('ss', $dbName, $table);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        fail("Table check failed for {$table}: {$err}");
    }
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if (!$exists) {
        fail("Missing required table: {$table}");
    }
}

ok('All required tables exist');

foreach ($requiredColumns as $table => $cols) {
    foreach ($cols as $col) {
        $stmt = $conn->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        if (!$stmt) {
            fail('Unable to prepare column check: ' . $conn->error);
        }
        $stmt->bind_param('sss', $dbName, $table, $col);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            fail("Column check failed for {$table}.{$col}: {$err}");
        }
        $res = $stmt->get_result();
        $exists = $res && $res->num_rows > 0;
        if ($res) {
            $res->free();
        }
        $stmt->close();

        if (!$exists) {
            fail("Missing required column: {$table}.{$col}");
        }
    }
}

ok('All required columns exist');

foreach ($minRows as $table => $min) {
    $result = $conn->query('SELECT COUNT(*) AS cnt FROM ' . $table);
    if (!$result) {
        fail("Unable to count rows for {$table}: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $result->free();
    $count = isset($row['cnt']) ? (int)$row['cnt'] : 0;
    if ($count < $min) {
        fail("Row count too low for {$table}: {$count} (min {$min})");
    }
    ok("Row count for {$table} = {$count} (min {$min})");
}

$requiredProcedures = ['update_mission_details', 'cancel_mission'];
foreach ($requiredProcedures as $proc) {
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? AND ROUTINE_TYPE='PROCEDURE' AND ROUTINE_NAME = ? LIMIT 1"
    );
    if (!$stmt) {
        fail('Unable to prepare routine check: ' . $conn->error);
    }
    $stmt->bind_param('ss', $dbName, $proc);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        fail("Routine check failed for {$proc}: {$err}");
    }
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if (!$exists) {
        fail("Missing stored procedure: {$proc}");
    }
}

ok('Stored procedures exist');

require_once __DIR__ . '/queries.php';

$queriesToRun = [
    ['type' => '1'],
    ['type' => '2'],
    ['type' => '3', 'post' => ['query3_group_by' => 'none']],
    ['type' => '3', 'post' => ['query3_group_by' => 'driver']],
    ['type' => '3', 'post' => ['query3_group_by' => 'vehicle']],
    ['type' => '4', 'post' => ['query4_group_by' => 'none']],
    ['type' => '4', 'post' => ['query4_group_by' => 'date']],
    ['type' => '4', 'post' => ['query4_group_by' => 'driver']],
    ['type' => '4', 'post' => ['query4_group_by' => 'location']],
    ['type' => '4', 'post' => ['query4_group_by' => 'vehicle']],
    ['type' => '5'],
    ['type' => '6'],
    ['type' => '7'],
    ['type' => '8'],
    ['type' => '9'],
];

$origPost = $_POST;
foreach ($queriesToRun as $q) {
    $_POST = $q['post'] ?? [];
    $payload = executeQuery($q['type'], $conn);
    if ($payload === false) {
        $_POST = $origPost;
        fail('Query ' . $q['type'] . ' failed: ' . $conn->error);
    }
    $rows = $payload['rows'] ?? [];
    ok('Query ' . $q['type'] . ' executed (rows=' . count($rows) . ')');
    validateQueryRequirements($q['type'], $rows, $_POST);
    ok('Query ' . $q['type'] . ' requirement checks passed');
}
$_POST = $origPost;

$stmt = $conn->prepare('CALL update_mission_details(?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    fail('Unable to prepare update_mission_details call: ' . $conn->error);
}

// Pick a real mission from current DB state (prefer Scheduled).
$missionId = 0;
$missionPick = $conn->query("SELECT mission_id FROM MISSION WHERE mission_status = 'S' ORDER BY mission_id LIMIT 1");
if ($missionPick && $missionPick->num_rows > 0) {
    $picked = $missionPick->fetch_assoc();
    $missionId = (int)($picked['mission_id'] ?? 0);
    $missionPick->free();
} else {
    if ($missionPick) {
        $missionPick->free();
    }
    $missionPickAny = $conn->query('SELECT mission_id FROM MISSION ORDER BY mission_id LIMIT 1');
    if ($missionPickAny && $missionPickAny->num_rows > 0) {
        $pickedAny = $missionPickAny->fetch_assoc();
        $missionId = (int)($pickedAny['mission_id'] ?? 0);
        $missionPickAny->free();
    } else {
        if ($missionPickAny) {
            $missionPickAny->free();
        }
        fail('No mission available for update_mission_details smoke check');
    }
}

$start = '2026-04-01 10:00:00';
$durationDays = 1;
$odoStart = 12345;
$odoEnd = 12500;
$status = 'C';

$stmt->bind_param('isiiis', $missionId, $start, $durationDays, $odoStart, $odoEnd, $status);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    clearAllResults($conn);
    fail('update_mission_details execution failed: ' . $err);
}
$stmt->close();
clearAllResults($conn);

$verify = $conn->query('SELECT actual_start_datetime, actual_end_datetime, odometer_start, odometer_end, mission_status, duration FROM MISSION WHERE mission_id = ' . (int)$missionId);
if (!$verify) {
    fail('Unable to verify mission update: ' . $conn->error);
}
$updated = $verify->fetch_assoc();
$verify->free();

if (!$updated) {
    fail('Selected mission not found for update verification');
}
if (($updated['mission_status'] ?? '') !== 'C') {
    fail('Selected mission status not updated as expected');
}
if (($updated['actual_start_datetime'] ?? null) === null || ($updated['actual_end_datetime'] ?? null) === null) {
    fail('Selected mission actual timestamps not updated as expected');
}
if (!isset($updated['duration']) || (float)$updated['duration'] <= 0) {
    fail('Selected mission duration was not updated as expected');
}

$startTs = strtotime((string)$updated['actual_start_datetime']);
$endTs = strtotime((string)$updated['actual_end_datetime']);
if ($startTs === false || $endTs === false) {
    fail('Unable to parse mission timestamps during update verification');
}
$expectedSeconds = $durationDays * 24 * 60 * 60;
if (($endTs - $startTs) !== $expectedSeconds) {
    fail('Selected mission end datetime did not change according to duration in days');
}
ok('update_mission_details smoke check passed');

$invalidStmt = $conn->prepare('CALL update_mission_details(?, ?, ?, ?, ?, ?)');
if (!$invalidStmt) {
    fail('Unable to prepare invalid update_mission_details call: ' . $conn->error);
}
$invalidDuration = 6;
$invalidStmt->bind_param('isiiis', $missionId, $start, $invalidDuration, $odoStart, $odoEnd, $status);
if ($invalidStmt->execute()) {
    $invalidStmt->close();
    clearAllResults($conn);
    fail('update_mission_details accepted invalid duration > 5 days');
}
$invalidStmt->close();
clearAllResults($conn);
ok('update_mission_details rejects invalid duration > 5 days');

$res = $conn->query('SELECT MAX(mission_id) AS max_id FROM MISSION');
if (!$res) {
    fail('Unable to select max mission_id: ' . $conn->error);
}
$r = $res->fetch_assoc();
$res->free();
$cancelId = ((int)($r['max_id'] ?? 1000)) + 1;

$insert = $conn->prepare(
    'INSERT INTO MISSION (mission_id, res_id, driver_id, vehicle_id, rendezvous_location, appointment_datetime, mission_status, duration) VALUES (?, 1, 1, 1, ?, ?, ?, 1)'
);
if (!$insert) {
    fail('Unable to prepare disposable mission insert: ' . $conn->error);
}
$loc = 'Airport';
$appt = '2026-04-02 09:00:00';
$st = 'S';
$insert->bind_param('isss', $cancelId, $loc, $appt, $st);
if (!$insert->execute()) {
    $err = $insert->error;
    $insert->close();
    fail('Unable to insert disposable mission: ' . $err);
}
$insert->close();

$cancelStmt = $conn->prepare('CALL cancel_mission(?, ?)');
if (!$cancelStmt) {
    fail('Unable to prepare cancel_mission call: ' . $conn->error);
}
$reason = 'smoke test';
$cancelStmt->bind_param('is', $cancelId, $reason);
if (!$cancelStmt->execute()) {
    $err = $cancelStmt->error;
    $cancelStmt->close();
    clearAllResults($conn);
    fail('cancel_mission execution failed: ' . $err);
}
$cancelStmt->close();
clearAllResults($conn);

$check = $conn->query('SELECT 1 FROM MISSION WHERE mission_id = ' . (int)$cancelId . ' LIMIT 1');
if (!$check) {
    fail('Unable to verify mission cancellation: ' . $conn->error);
}
$stillThere = $check->num_rows > 0;
$check->free();

if ($stillThere) {
    fail('cancel_mission did not remove the disposable mission');
}
ok('cancel_mission smoke check passed');

$conn->close();
ok('All smoke tests passed');
