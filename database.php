<?php
/**
 * database.php
 * Purpose: Shared MySQL connection helper (mysqli) for the web UI.
 * Notes: Uses DB constants from config.php and credentials stored in $_SESSION.
 */

require_once __DIR__ . '/config.php';

/**
 * Establishes a database connection using credentials stored in the session.
 *
 * @return mysqli|null A mysqli object on success, or null on failure.
 */
function getDbConnection(): ?mysqli {
    if (!isset($_SESSION['db_user']) || !isset($_SESSION['db_password'])) {
        return null;
    }

    $conn = new mysqli(DB_HOST, $_SESSION['db_user'], $_SESSION['db_password'], DB_NAME);

    if ($conn->connect_error) {
        return null; // Should log error in a real application
    }

    return $conn;
}
