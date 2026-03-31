<?php

function executeTransaction(string $transactionType, mysqli $conn) {
    $message = '';
    $detailsTitle = '';
    $detailsRows = [];

    if ($transactionType === 'update_mission') {
        $missionId = isset($_POST['mission_id']) ? intval($_POST['mission_id']) : 0;
        $missionStatus = $_POST['mission_status'] ?? '';

        if (empty($missionId) || empty($missionStatus)) {
            return [
                'message' => 'Mission ID and Mission Status are required for Update Mission transaction.',
                'isError' => true,
                'detailsTitle' => '',
                'detailsRows' => [],
            ];
        }

        if (!in_array($missionStatus, ['P', 'S', 'C'], true)) {
            return [
                'message' => 'Invalid mission status (must be P, S, or C).',
                'isError' => true,
                'detailsTitle' => '',
                'detailsRows' => [],
            ];
        }

        // If completing the mission, we require the completion details and call the stored procedure.
        if ($missionStatus === 'C') {
            $actualStart = $_POST['actual_start_datetime'] ?? '';
            $duration = isset($_POST['duration_hours']) ? intval($_POST['duration_hours']) : 0;
            $odometerStart = isset($_POST['odometer_start']) ? intval($_POST['odometer_start']) : 0;
            $odometerEnd = isset($_POST['odometer_end']) ? intval($_POST['odometer_end']) : 0;

            if (empty($actualStart) || empty($duration) || empty($odometerStart) || empty($odometerEnd)) {
                return [
                    'message' => 'Actual start, duration (hours), and odometer values are required when setting status to Completed.',
                    'isError' => true,
                    'detailsTitle' => '',
                    'detailsRows' => [],
                ];
            }

            // Convert datetime-local to MySQL format
            $actualStart = str_replace('T', ' ', $actualStart);

            $stmt = $conn->prepare('CALL update_mission_details(?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                return [
                    'message' => 'Error preparing Update Mission: ' . $conn->error,
                    'isError' => true,
                    'detailsTitle' => '',
                    'detailsRows' => [],
                ];
            }

            // (mission_id INT, start_datetime DATETIME, duration_hours INT, odometer_start INT, odometer_end INT, status CHAR(1))
            $stmt->bind_param('isiiis', $missionId, $actualStart, $duration, $odometerStart, $odometerEnd, $missionStatus);

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                return [
                    'message' => 'Error executing Update Mission: ' . $err,
                    'isError' => true,
                    'detailsTitle' => '',
                    'detailsRows' => [],
                ];
            }

            $stmt->close();
            $message = '✅ Mission ' . $missionId . ' updated successfully!';
        } else {
            // Status-only update (Pending/Scheduled). Triggers enforce that actual values remain NULL.
            $stmt = $conn->prepare('UPDATE MISSION SET mission_status = ? WHERE mission_id = ?');
            if (!$stmt) {
                return [
                    'message' => 'Error preparing mission status update: ' . $conn->error,
                    'isError' => true,
                    'detailsTitle' => '',
                    'detailsRows' => [],
                ];
            }

            $stmt->bind_param('si', $missionStatus, $missionId);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                return [
                    'message' => 'Error executing mission status update: ' . $err,
                    'isError' => true,
                    'detailsTitle' => '',
                    'detailsRows' => [],
                ];
            }

            if ($stmt->affected_rows === 0) {
                // Could be "mission not found" OR "status unchanged".
                $exists = $conn->query('SELECT 1 FROM MISSION WHERE mission_id = ' . (int)$missionId . ' LIMIT 1');
                if (!$exists || $exists->num_rows === 0) {
                    $stmt->close();
                    return [
                        'message' => 'Mission not found.',
                        'isError' => true,
                        'detailsTitle' => '',
                        'detailsRows' => [],
                    ];
                }
            }

            $stmt->close();
            $message = '✅ Mission ' . $missionId . ' status updated successfully!';
        }

        $detailsTitle = 'Updated Mission Details:';
        $verifyResult = $conn->query('SELECT * FROM MISSION WHERE mission_id = ' . $missionId);
        if ($verifyResult) {
            $detailsRows = $verifyResult->fetch_all(MYSQLI_ASSOC);
        }

        return [
            'message' => $message,
            'isError' => false,
            'detailsTitle' => $detailsTitle,
            'detailsRows' => $detailsRows,
        ];
    }

    if ($transactionType === 'cancel_mission') {
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

            $message = '✅ ' . $resultMessage;
            $detailsTitle = 'Cancelled Mission Details:';

            $verifyResult = $conn->query('SELECT * FROM MISSION WHERE mission_id = ' . $missionId);
            if ($verifyResult) {
                $detailsRows = $verifyResult->fetch_all(MYSQLI_ASSOC);
            }

            $stmt->close();
            return [
                'message' => $message,
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

    return [
        'message' => "Unknown transaction type: {$transactionType}",
        'isError' => true,
        'detailsTitle' => '',
        'detailsRows' => [],
    ];
}
