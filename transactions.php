<?php

function executeTransaction(string $transactionType, mysqli $conn) {
    $message = '';
    $detailsTitle = '';
    $detailsRows = [];

    if ($transactionType === 'update_mission') {
        $missionId = isset($_POST['mission_id']) ? intval($_POST['mission_id']) : 0;
        $actualStart = $_POST['actual_start_datetime'] ?? '';
        $actualEnd = $_POST['actual_end_datetime'] ?? '';
        $odometerStart = isset($_POST['odometer_start']) ? intval($_POST['odometer_start']) : 0;
        $odometerEnd = isset($_POST['odometer_end']) ? intval($_POST['odometer_end']) : 0;
        $missionStatus = $_POST['mission_status'] ?? '';

        if (empty($missionId) || empty($actualStart) || empty($actualEnd) || empty($odometerStart) || empty($odometerEnd) || empty($missionStatus)) {
            return [
                'message' => 'All fields are required for Update Mission transaction.',
                'isError' => true,
                'detailsTitle' => '',
                'detailsRows' => [],
            ];
        }

        // Convert datetime-local to MySQL format
        $actualStart = str_replace('T', ' ', $actualStart);
        $actualEnd = str_replace('T', ' ', $actualEnd);

        $stmt = $conn->prepare('CALL update_mission_details(?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return [
                'message' => 'Error preparing Update Mission: ' . $conn->error,
                'isError' => true,
                'detailsTitle' => '',
                'detailsRows' => [],
            ];
        }

        // Matches original: missionId(int), actualStart(string), actualEnd(string), odometerStart(int), odometerEnd(int), status(string)
        $stmt->bind_param('issiis', $missionId, $actualStart, $actualEnd, $odometerStart, $odometerEnd, $missionStatus);

        if ($stmt->execute()) {
            $message = '✅ Mission ' . $missionId . ' updated successfully!';
            $detailsTitle = 'Updated Mission Details:';

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
            'message' => 'Error executing Update Mission: ' . $err,
            'isError' => true,
            'detailsTitle' => '',
            'detailsRows' => [],
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
