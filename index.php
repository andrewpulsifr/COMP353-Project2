<?php
session_start();
date_default_timezone_set('America/Toronto');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/transactions.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle login form submission
$isLoggedIn = isset($_SESSION['db_user']) && isset($_SESSION['db_password']);
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['db_username'] ?? '';
    $password = $_POST['db_password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            // Test the connection
            $testConn = new mysqli(DB_HOST, $username, $password, DB_NAME);
            
            if ($testConn->connect_error) {
                $loginError = 'Invalid credentials. Please try again.';
            } else {
                $testConn->close();
                $_SESSION['db_user'] = $username;
                $_SESSION['db_password'] = $password;
                $isLoggedIn = true;
                // Redirect to clear POST data
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $loginError = 'Invalid credentials. Please try again.';
        }
    } else {
        $loginError = 'Please enter both username and password';
    }
}

// Initialize variables
$queryResult = null;
$queryTitle = '';
$hideColumns = [];
$selectedQuery = '';
$queryExecuted = false;
$queryError = '';
$transactionResult = '';
$transactionIsError = false;
$transactionDetailsTitle = '';
$transactionDetailsRows = [];
$dbTables = [];

if ($isLoggedIn) {
    $conn = getDbConnection();
    if ($conn) {
        // Handle query execution
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executeQuery'])) {
            $selectedQuery = $_POST['query_type'] ?? '';

            if ($selectedQuery !== '') {
                $payload = executeQuery($selectedQuery, $conn);
                if ($payload === false) {
                    $queryError = "Error executing query: " . $conn->error;
                } else {
                    $queryTitle = $payload['title'] ?? '';
                    $hideColumns = $payload['hideColumns'] ?? [];
                    $queryResult = $payload['rows'] ?? [];
                    $queryExecuted = true;
                }
            }
        }

        // Handle transaction execution
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executeTransaction'])) {
            $transactionType = $_POST['transaction_type'] ?? '';
            if ($transactionType) {
                $transactionPayload = executeTransaction($transactionType, $conn);
                if (is_array($transactionPayload)) {
                    $transactionResult = $transactionPayload['message'] ?? '';
                    $transactionIsError = (bool)($transactionPayload['isError'] ?? false);
                    $transactionDetailsTitle = $transactionPayload['detailsTitle'] ?? '';
                    $transactionDetailsRows = $transactionPayload['detailsRows'] ?? [];
                } else {
                    $transactionResult = (string)$transactionPayload;
                }
            }
        }
        
        // View all tables 
        $tablesResult = $conn->query('SHOW TABLES');
        if ($tablesResult) {
            while ($table = $tablesResult->fetch_array()) {
                $tableName = $table[0];
                $contentResult = $conn->query('SELECT * FROM ' . $tableName);
                if ($contentResult) {
                    $dbTables[$tableName] = $contentResult->fetch_all(MYSQLI_ASSOC);
                }
            }
        }

        $conn->close();
    } else {
        // Could not connect to DB with session credentials, force logout.
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}


// Render the view
require_once __DIR__ . '/main_view.php';
