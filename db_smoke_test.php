<?php

declare(strict_types=1);

// CLI smoke test for the COMP353 Project DB.
// Connects with environment variables (no web session required).
//
// Usage:
//   DB_USER=... DB_PASSWORD=... php db_smoke_test.php

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
}
$_POST = $origPost;

$stmt = $conn->prepare('CALL update_mission_details(?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    fail('Unable to prepare update_mission_details call: ' . $conn->error);
}

$missionId = 1;
$start = '2026-04-01 10:00:00';
$durationHours = 8;
$odoStart = 12345;
$odoEnd = 12500;
$status = 'C';

$stmt->bind_param('isiiis', $missionId, $start, $durationHours, $odoStart, $odoEnd, $status);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    clearAllResults($conn);
    fail('update_mission_details execution failed: ' . $err);
}
$stmt->close();
clearAllResults($conn);

$verify = $conn->query('SELECT actual_start_datetime, actual_end_datetime, odometer_start, odometer_end, mission_status, duration FROM MISSION WHERE mission_id = 1');
if (!$verify) {
    fail('Unable to verify mission update: ' . $conn->error);
}
$updated = $verify->fetch_assoc();
$verify->free();

if (!$updated) {
    fail('Mission 1 not found for update verification');
}
if (($updated['mission_status'] ?? '') !== 'C') {
    fail('Mission 1 status not updated as expected');
}
if (($updated['actual_start_datetime'] ?? null) === null || ($updated['actual_end_datetime'] ?? null) === null) {
    fail('Mission 1 actual timestamps not updated as expected');
}
if (!isset($updated['duration']) || (float)$updated['duration'] <= 0) {
    fail('Mission 1 duration was not updated as expected');
}
ok('update_mission_details smoke check passed');

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
$st = 'P';
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
