<?php

/**
 * queries.php
 * Purpose: Implements the required SELECT queries (1–9) for the COMP353 project.
 */

/**
 * Executes one of the original SELECT queries (1-9)
 *
 * @return array{title:string, rows:array<int, array<string, mixed>>, hideColumns:array<int, string>}|false
 */
function executeQuery(string $queryType, mysqli $conn) {
    // Accept both legacy values like "q1" and original values like "1".
    if (substr($queryType, 0, 1) === 'q') {
        $queryType = substr($queryType, 1);
    }

    $query = '';
    $title = '';
    $hideColumns = [];

    switch ($queryType) {
        case '1':
            $query = "SELECT client_id, client_name, client_addr, client_phone, client_type\n"
                . "FROM CLIENT WHERE client_type = 'Business' ORDER BY client_name";
            $title = 'Query 1: Business Customers';
            break;

        case '2':
            $query = "SELECT res_id, client_id, reservation_date,\n"
                . "CASE WHEN res_status = 'P' THEN 'Pending'\n"
                . "     WHEN res_status = 'C' THEN 'Completed'\n"
                . "     WHEN res_status = 'X' THEN 'Cancelled'\n"
                . "END as res_status,\n"
                . "requested_vehicle_type, expected_duration, appointment_datetime\n"
                . "requested_vehicle_type, expected_duration, appointment_datetime,\n"
                . "DATE_ADD(appointment_datetime, INTERVAL expected_duration DAY) AS expected_end_datetime\n"
                . "FROM RESERVATION WHERE res_id > 1 ORDER BY res_id";
            $title = 'Query 2: Reservations (res_id > 1)';
            break;

        case '3':
            $groupBy = $_POST['query3_group_by'] ?? 'none';

            if ($groupBy === 'driver') {
                $query = "SELECT d.driver_id,\n"
                    . "COALESCE(d.driver_first_name, 'N/A') as driver_first_name,\n"
                    . "COALESCE(d.driver_last_name, 'N/A') as driver_last_name,\n"
                    . "COUNT(DISTINCT m.mission_id) as mission_count\n"
                    . "FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id\n"
                    . "GROUP BY d.driver_id, d.driver_first_name, d.driver_last_name\n"
                    . "ORDER BY d.driver_id";
                $title = 'Query 3: Active Drivers (Grouped by Driver)';
                $hideColumns = ['vehicle_id', 'vehicle_type', 'vehicle_brand'];
            } elseif ($groupBy === 'vehicle') {
                $query = "SELECT v.vehicle_id, v.vehicle_type, v.vehicle_brand,\n"
                    . "COUNT(DISTINCT m.mission_id) as mission_count\n"
                    . "FROM VEHICLE v JOIN MISSION m ON v.vehicle_id = m.vehicle_id\n"
                    . "GROUP BY v.vehicle_id, v.vehicle_type, v.vehicle_brand\n"
                    . "ORDER BY v.vehicle_id";
                $title = 'Query 3: Active Vehicles (Grouped by Vehicle)';
                $hideColumns = ['driver_id', 'driver_first_name', 'driver_last_name'];
            } else {
                $query = "SELECT DISTINCT d.driver_id,\n"
                    . "COALESCE(d.driver_first_name, 'N/A') as driver_first_name,\n"
                    . "COALESCE(d.driver_last_name, 'N/A') as driver_last_name,\n"
                    . "v.vehicle_id, v.vehicle_type, v.vehicle_brand\n"
                    . "FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id\n"
                    . "JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id\n"
                    . "ORDER BY d.driver_id, v.vehicle_id";
                $title = 'Query 3: Active Drivers and Vehicles in Missions';
            }
            break;

        case '4':
            $groupBy = $_POST['query4_group_by'] ?? 'none';

            if ($groupBy === 'date') {
                $query = "SELECT DATE(m.appointment_datetime) as appointment_date,\n"
                    . "COUNT(DISTINCT m.mission_id) as mission_count\n"
                    . "FROM MISSION m\n"
                    . "WHERE DATE(m.appointment_datetime) BETWEEN '2026-03-11' AND '2026-03-18'\n"
                    . "GROUP BY DATE(m.appointment_datetime)\n"
                    . "ORDER BY appointment_date";
                $title = 'Query 4: Missions Between Mar 11-18, 2026 (Grouped by Date)';
            } elseif ($groupBy === 'driver') {
                $query = "SELECT d.driver_id, COALESCE(d.driver_first_name, 'N/A') as driver_first_name,\n"
                    . "COALESCE(d.driver_last_name, 'N/A') as driver_last_name,\n"
                    . "COUNT(DISTINCT m.mission_id) as mission_count\n"
                    . "FROM MISSION m JOIN DRIVER d ON m.driver_id = d.driver_id\n"
                    . "WHERE DATE(m.appointment_datetime) BETWEEN '2026-03-11' AND '2026-03-18'\n"
                    . "GROUP BY d.driver_id, d.driver_first_name, d.driver_last_name\n"
                    . "ORDER BY d.driver_id";
                $title = 'Query 4: Missions Between Mar 11-18, 2026 (Grouped by Driver)';
            } elseif ($groupBy === 'location') {
                $query = "SELECT m.rendezvous_location,\n"
                    . "COUNT(DISTINCT m.mission_id) as mission_count\n"
                    . "FROM MISSION m\n"
                    . "WHERE DATE(m.appointment_datetime) BETWEEN '2026-03-11' AND '2026-03-18'\n"
                    . "GROUP BY m.rendezvous_location\n"
                    . "ORDER BY m.rendezvous_location";
                $title = 'Query 4: Missions Between Mar 11-18, 2026 (Grouped by Location)';
            } elseif ($groupBy === 'vehicle') {
                $query = "SELECT v.vehicle_id, v.vehicle_type, v.vehicle_brand,\n"
                    . "COUNT(DISTINCT m.mission_id) as mission_count\n"
                    . "FROM MISSION m JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id\n"
                    . "WHERE DATE(m.appointment_datetime) BETWEEN '2026-03-11' AND '2026-03-18'\n"
                    . "GROUP BY v.vehicle_id, v.vehicle_type, v.vehicle_brand\n"
                    . "ORDER BY v.vehicle_id";
                $title = 'Query 4: Missions Between Mar 11-18, 2026 (Grouped by Vehicle)';
            } else {
                $query = "SELECT m.mission_id, DATE(m.appointment_datetime) as appointment_date, TIME(m.appointment_datetime) as appointment_time,\n"
                    . "CASE WHEN m.mission_status = 'S' THEN 'Scheduled'\n"
                    . "     WHEN m.mission_status = 'C' THEN 'Completed'\n"
                    . "END as mission_status,\n"
                    . "d.driver_id, COALESCE(d.driver_first_name, 'N/A') as driver_first_name,\n"
                    . "COALESCE(d.driver_last_name, 'N/A') as driver_last_name,\n"
                    . "d.driver_licence_type, v.vehicle_id, v.vehicle_type,\n"
                    . "v.vehicle_brand, m.rendezvous_location, r.expected_duration\n"
                    . "FROM MISSION m JOIN DRIVER d ON m.driver_id = d.driver_id\n"
                    . "JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id\n"
                    . "JOIN RESERVATION r ON r.res_id = m.res_id\n"
                    . "WHERE DATE(m.appointment_datetime) BETWEEN '2026-03-11' AND '2026-03-18'\n"
                    . "ORDER BY m.mission_id";
                $title = 'Query 4: Missions Between Mar 11-18, 2026';
            }
            break;

        case '5':
            $query = "SELECT DISTINCT c.client_id, c.client_name, c.client_addr,\n"
                . "c.client_phone, c.client_type, i.invoice_id, i.invoice_date,\n"
                . "CASE WHEN p.pay_status = 'C' THEN 'Completed'\n"
                . "     WHEN p.pay_status = 'P' THEN 'Pending'\n"
                . "END as pay_status,\n"
                . "CONCAT('$', FORMAT(p.amount, 2)) as amount\n"
                . "FROM CLIENT c JOIN INVOICE i ON c.client_id = i.client_id\n"
                . "JOIN PAYMENT p ON i.invoice_id = p.invoice_id WHERE p.pay_status = 'P'\n"
                . "ORDER BY c.client_id, i.invoice_id";
            $title = 'Query 5: Customers with Unpaid Invoices';
            break;

        case '6':
            $query = "SELECT DISTINCT d.driver_id,\n"
                . "COALESCE(d.driver_first_name, 'N/A') as driver_first_name,\n"
                . "COALESCE(d.driver_last_name, 'N/A') as driver_last_name, v.vehicle_id, v.vehicle_brand\n"
                . "FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id\n"
                . "JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id WHERE v.vehicle_brand = 'GMC'\n"
                . "ORDER BY d.driver_id";
            $title = 'Query 6: Drivers Who Drove GMC Vehicles';
            break;

        case '7':
            $query = "SELECT c.client_id, c.client_name, c.client_addr, c.client_phone, i.invoice_id, i.invoice_date,\n"
                . "CONCAT('$', FORMAT(SUM(il.rental_cost), 2)) as total_rental_cost\n"
                . "FROM CLIENT c JOIN INVOICE i ON c.client_id = i.client_id\n"
                . "JOIN INVOICE_LINE il ON i.invoice_id = il.invoice_id\n"
                . "GROUP BY c.client_id, c.client_name, c.client_addr, c.client_phone, i.invoice_id, i.invoice_date\n"
                . "HAVING SUM(il.rental_cost) > 1000.00 ORDER BY SUM(il.rental_cost) DESC, c.client_id";
            $title = 'Query 7: Customers with Invoices > $1000';
            break;

        case '8':
            $query = "SELECT c.client_id, c.client_name, c.client_addr, c.client_phone, c.client_type,\n"
                . "COUNT(i.invoice_id) as invoice_count, CONCAT('$',\n"
                . "FORMAT(COALESCE(SUM(il.rental_cost), 0), 2)) as total_rental_cost\n"
                . "FROM CLIENT c LEFT JOIN INVOICE i ON c.client_id = i.client_id\n"
                . "LEFT JOIN INVOICE_LINE il ON i.invoice_id = il.invoice_id\n"
                . "GROUP BY c.client_id, c.client_name, c.client_addr, c.client_phone, c.client_type\n"
                . "ORDER BY invoice_count DESC, c.client_id";
            $title = 'Query 8: Customer Invoice Summary';
            break;

        case '9':
            $query = "SELECT d.driver_id, COALESCE(d.driver_first_name, 'N/A') as driver_first_name,\n"
                . "COALESCE(d.driver_last_name, 'N/A') as driver_last_name,\n"
                . "MAX((m.odometer_end - m.odometer_start)) as max_kilometers_traveled\n"
                . "FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id\n"
                . "WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-01' AND '2026-03-31'\n"
                . "AND (m.odometer_end - m.odometer_start) > 7000\n"
                . "GROUP BY d.driver_id, d.driver_first_name, d.driver_last_name\n"
                . "ORDER BY max_kilometers_traveled DESC";
            $title = 'Query 9: High-Mileage Drivers (>7000km, Feb-Mar 2026)';
            break;
    }

    if ($query === '') {
        return false;
    }

    $result = $conn->query($query);
    if (!$result) {
        return false;
    }

    return [
        'title' => $title,
        'rows' => $result->fetch_all(MYSQLI_ASSOC),
        'hideColumns' => $hideColumns,
    ];
}
