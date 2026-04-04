<?php

/**
 * transactions.php
 * Purpose: Implements required transactions (complete mission, cancel mission) for the COMP353 project.
 */

/**
 * Dispatches transaction handling to the appropriate transaction function.
 *
 * @param string $transactionType Transaction key posted by the UI.
 * @param mysqli $conn Active database connection.
 * @return array{message:string,isError:bool,detailsTitle:string,detailsRows:array<int,array<string,mixed>>}
 */
function executeTransaction(string $transactionType, mysqli $conn): array {
    if ($transactionType === 'update_mission') {
        return executeCompleteMissionTransaction($conn);
    }

    if ($transactionType === 'cancel_mission') {
        return executeCancelMissionTransaction($conn);
    }

    return [
        'message' => "Unknown transaction type: {$transactionType}",
        'isError' => true,
        'detailsTitle' => '',
        'detailsRows' => [],
    ];
}

/**
 * Completes a mission by invoking update_mission_details with provided actual values.
 *
 * @param mysqli $conn Active database connection.
 * @return array{message:string,isError:bool,detailsTitle:string,detailsRows:array<int,array<string,mixed>>}
 */
function executeCompleteMissionTransaction(mysqli $conn): array {
    $missionId = isset($_POST['mission_id']) ? intval($_POST['mission_id']) : 0;

    if (empty($missionId)) {
        return [
            'message' => 'Mission ID is required for Complete Mission transaction.',
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
        ];
    }

    $actualStart = $_POST['actual_start_datetime'] ?? '';
    $durationDays = isset($_POST['duration_days']) ? intval($_POST['duration_days']) : 0;
    $odometerStart = isset($_POST['odometer_start']) ? intval($_POST['odometer_start']) : 0;
    $odometerEnd = isset($_POST['odometer_end']) ? intval($_POST['odometer_end']) : 0;

    if (empty($actualStart) || empty($durationDays) || empty($odometerStart) || empty($odometerEnd)) {
        return [
            'message' => 'Actual start, duration (days), and odometer values are required to complete a mission.',
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
        ];
    }

    // Convert datetime-local to MySQL format
    $actualStart = str_replace('T', ' ', $actualStart);

    $missionStatus = 'C';
    $stmt = $conn->prepare('CALL update_mission_details(?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return [
            'message' => 'Error preparing Complete Mission: ' . $conn->error,
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
        ];
    }

    // (mission_id INT, start_datetime DATETIME, mission_duration_days FLOAT, odometer_start INT, odometer_end INT, status CHAR(1))
    $stmt->bind_param('isdiis', $missionId, $actualStart, $durationDays, $odometerStart, $odometerEnd, $missionStatus);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        return [
            'message' => 'Error executing Complete Mission: ' . $err,
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
        ];
    }

    $stmt->close();
    $detailsRows = [];
    $detailsTitle = 'Updated Mission Details:';
    $verifyResult = $conn->query('SELECT * FROM MISSION WHERE mission_id = ' . $missionId);
    if ($verifyResult) {
        $detailsRows = $verifyResult->fetch_all(MYSQLI_ASSOC);
    }

    return [
        'message' => 'Mission ' . $missionId . ' completed successfully!',
        'isError' => false,
        'detailsTitle' => $detailsTitle,
        'detailsRows' => $detailsRows,
    ];
}

/**
 * Cancels a mission by invoking cancel_mission and returning procedure feedback.
 *
 * @param mysqli $conn Active database connection.
 * @return array{message:string,isError:bool,detailsTitle:string,detailsRows:array<int,array<string,mixed>>}
 */
function executeCancelMissionTransaction(mysqli $conn): array {
    $missionId = isset($_POST['cancel_mission_id']) ? intval($_POST['cancel_mission_id']) : 0;
    $reason = $_POST['cancellation_reason'] ?? '';

    if (empty($missionId) || empty($reason)) {
        return [
            'message' => 'All fields are required for Cancel Mission transaction.',
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
        ];
    }

    $stmt = $conn->prepare('CALL cancel_mission(?, ?)');
    if (!$stmt) {
        return [
            'message' => 'Error preparing Cancel Mission: ' . $conn->error,
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
        ];
    }

    $stmt->bind_param('is', $missionId, $reason);

    if ($stmt->execute()) {
        $resultMessage = 'Mission cancelled successfully';

        // Stored proc may return a result set with a 'result' field.
        if (method_exists($stmt, 'get_result')) {
            $resultSet = $stmt->get_result();
            if ($resultSet) {
                $row = $resultSet->fetch_assoc();
                if ($row && isset($row['result'])) {
                    $resultMessage = $row['result'];
                }
            }
        }

        $detailsRows = [];
        $detailsTitle = 'Cancelled Mission Details:';
        $verifyResult = $conn->query('SELECT * FROM MISSION WHERE mission_id = ' . $missionId);
        if ($verifyResult) {
            $detailsRows = $verifyResult->fetch_all(MYSQLI_ASSOC);
        }

        $stmt->close();
        return [
            'message' => $resultMessage,
            'isError' => false,
            'detailsTitle' => $detailsTitle,
            'detailsRows' => $detailsRows,
        ];
    }

    $err = $stmt->error;
    $stmt->close();
    return [
        'message' => 'Error executing Cancel Mission: ' . $err,
        'isError' => true,
        'detailsTitle' => '',
        'detailsRows' => [],
    ];
}
