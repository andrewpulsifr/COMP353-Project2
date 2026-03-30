<?php
session_start();
date_default_timezone_set('America/Toronto');

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
        // Test the connection
        $testConn = new mysqli("qwc353.encs.concordia.ca", $username, $password, "qwc353_4");
        
        if ($testConn->connect_error) {
            $loginError = 'Invalid credentials. Please try again.';
        } else {
            $testConn->close();
            $_SESSION['db_user'] = $username;
            $_SESSION['db_password'] = $password;
            $isLoggedIn = true;
        }
    } else {
        $loginError = 'Please enter both username and password';
    }
}

// Initialize variables
$selectedQuery = '';
$queryResult = null;
$queryError = '';
$transactionResult = '';

// Handle query execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executeQuery'])) {
    $selectedQuery = $_POST['query_type'] ?? '';
}

// Handle transaction execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executeTransaction'])) {
    $transactionType = $_POST['transaction_type'] ?? '';
}
?>
<HTML>
<HEAD>
  <TITLE>COMP333 Project - RENTRUCK Database</TITLE>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 20px;
      background-color: #f5f5f5;
    }
    h1 {
      color: #333;
      border-bottom: 3px solid #4CAF50;
      padding-bottom: 10px;
    }
    #queryPanel, #transactionPanel, #dbContent, #resultsPanel {
      display: none;
      background-color: white;
      padding: 20px;
      margin: 15px 0;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    #queryPanel.show, #transactionPanel.show, #dbContent.show, #resultsPanel.show {
      display: block;
    }
    .button-group {
      margin: 20px 0;
    }
    button {
      padding: 10px 20px;
      margin: 5px;
      font-size: 14px;
      cursor: pointer;
      background-color: #4CAF50;
      color: white;
      border: none;
      border-radius: 4px;
      transition: background-color 0.3s;
    }
    button:hover {
      background-color: #45a049;
    }
    button.hide-btn {
      background-color: #f44336;
    }
    button.hide-btn:hover {
      background-color: #da190b;
    }
    button.query-btn {
      background-color: #2196F3;
      font-size: 12px;
      padding: 8px 15px;
    }
    button.query-btn:hover {
      background-color: #0b7dda;
    }
    button.transaction-btn {
      background-color: #ff9800;
      font-size: 12px;
      padding: 8px 15px;
    }
    button.transaction-btn:hover {
      background-color: #e68900;
    }
    .form-group {
      margin-bottom: 15px;
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 10px;
      align-items: center;
    }
    .form-group label {
      font-weight: bold;
      color: #333;
    }
    .form-group input, .form-group textarea {
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-sizing: border-box;
      font-size: 14px;
    }
    .form-group textarea {
      grid-column: 1 / -1;
      resize: vertical;
      min-height: 80px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin: 15px 0;
      background-color: #fff;
    }
    table, th, td {
      border: 1px solid #ddd;
    }
    th {
      background-color: #4CAF50;
      color: white;
      padding: 12px;
      text-align: left;
    }
    td {
      padding: 10px;
    }
    tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    .error {
      color: red;
      background-color: #ffe6e6;
      padding: 10px;
      border: 1px solid #ff0000;
      border-radius: 4px;
      margin: 10px 0;
    }
    .success {
      color: green;
      background-color: #e6ffe6;
      padding: 10px;
      border: 1px solid #00cc00;
      border-radius: 4px;
      margin: 10px 0;
    }
    .query-buttons-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 10px;
      margin: 15px 0;
    }
    .section-title {
      font-size: 18px;
      font-weight: bold;
      color: #333;
      margin-top: 20px;
      margin-bottom: 10px;
      border-left: 4px solid #4CAF50;
      padding-left: 10px;
    }
    .no-results {
      color: #666;
      font-style: italic;
      padding: 20px;
      text-align: center;
    }
    .query3-options {
      background-color: #f9f9f9;
      padding: 15px;
      border-radius: 4px;
      margin: 15px 0;
      display: none;
    }
    .query3-options.show {
      display: block;
    }
    .query3-options label {
      font-weight: bold;
      margin-right: 10px;
    }
    .query3-options select {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      cursor: pointer;
    }
    .query4-options {
      background-color: #f9f9f9;
      padding: 15px;
      border-radius: 4px;
      margin: 15px 0;
      display: none;
    }
    .query4-options.show {
      display: block;
    }
    .query4-options label {
      font-weight: bold;
      margin-right: 10px;
    }
    .query4-options select {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      cursor: pointer;
    }
  </style>
  <script>
    function showPanel(panelId) {
      // If showing results, just show query and results panels without hiding others
      if (panelId === 'resultsPanel') {
        document.getElementById("queryPanel").classList.add("show");
        document.getElementById("resultsPanel").classList.add("show");
      } else if (panelId) {
        // For other panels, hide all first then show the requested one
        document.getElementById("queryPanel").classList.remove("show");
        document.getElementById("transactionPanel").classList.remove("show");
        document.getElementById("dbContent").classList.remove("show");
        document.getElementById("resultsPanel").classList.remove("show");
        document.getElementById("query3-options").classList.remove("show");
        document.getElementById("query4-options").classList.remove("show");
        // Show the requested panel
        document.getElementById(panelId).classList.add("show");
      }
    }
    function hideAll() {
      document.getElementById("queryPanel").classList.remove("show");
      document.getElementById("transactionPanel").classList.remove("show");
      document.getElementById("dbContent").classList.remove("show");
      document.getElementById("resultsPanel").classList.remove("show");
      document.getElementById("resultContent").innerHTML = "";
      document.getElementById("query3-options").classList.remove("show");
      document.getElementById("query4-options").classList.remove("show");
    }
    function executeQuery(queryType) {
      document.getElementById("selectedQuery").value = queryType;

      // For Query 3 and 4, show dropdown AFTER results (don't prevent submit)
      // The dropdown will be shown by the results panel
      if (queryType !== '3' && queryType !== '4') {
        document.getElementById("query3-options").classList.remove("show");
        document.getElementById("query3GroupBy").value = "none";
        document.getElementById("query4-options").classList.remove("show");
        document.getElementById("query4GroupBy").value = "none";
      }

      document.getElementById("queryForm").submit();
    }
    function updateQuery3GroupBy(value) {
      document.getElementById("query3GroupBy").value = value;
      document.getElementById("selectedQuery").value = '3';
      document.getElementById("queryForm").submit();
    }
    function updateQuery4GroupBy(value) {
      document.getElementById("query4GroupBy").value = value;
      document.getElementById("selectedQuery").value = '4';
      document.getElementById("queryForm").submit();
    }
  </script>
</HEAD>
<BODY>
<H1>🚚 RENTRUCK Database Management System</H1>

<div class="button-group">
  <?php if ($isLoggedIn): ?>
    <button onclick="showPanel('queryPanel')">📊 Run Queries</button>
    <button onclick="showPanel('transactionPanel')">💾 Execute Transactions</button>
    <button onclick="showPanel('dbContent')">👁️ View All Tables</button>
    <button class="hide-btn" onclick="hideAll()">Hide All</button>
    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=1" style="text-decoration: none;">
      <button class="hide-btn">🚪 Logout</button>
    </a>
  <?php endif; ?>
</div>

<?php if (!$isLoggedIn): ?>
  <div style="margin: 20px 0; padding: 20px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; max-width: 400px;">
    <h2>Database Login</h2>
    <?php if ($loginError): ?>
      <p style="color: red;"><?php echo htmlspecialchars($loginError); ?></p>
    <?php endif; ?>
    <form method="POST">
      <div style="margin-bottom: 15px;">
        <label for="db_username">Username:</label><br>
        <input type="text" id="db_username" name="db_username" required style="width: 100%; padding: 8px; box-sizing: border-box;">
      </div>
      <div style="margin-bottom: 15px;">
        <label for="db_password">Password:</label><br>
        <input type="password" id="db_password" name="db_password" required style="width: 100%; padding: 8px; box-sizing: border-box;">
      </div>
      <button type="submit" name="login" value="1" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">Login</button>
    </form>
  </div>
<?php else: ?>
  <p style="margin: 10px 0; color: #666;">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['db_user']); ?></strong></p>

<!-- =====================================================
     QUERY EXECUTION PANE
     ===================================================== -->
<div id="queryPanel">
  <h2>📊 Query Execution</h2>

  <div class="section-title">SELECT Queries (1-9)</div>
  <div class="query-buttons-row">
    <button class="query-btn" onclick="executeQuery('1')">1️⃣ Business Customers</button>
    <button class="query-btn" onclick="executeQuery('2')">2️⃣ Reservations > 1</button>
    <button class="query-btn" onclick="executeQuery('3')">3️⃣ Active Drivers/Vehicles</button>
    <button class="query-btn" onclick="executeQuery('4')">4️⃣ Weekly Missions</button>
    <button class="query-btn" onclick="executeQuery('5')">5️⃣ Unpaid Invoices</button>
    <button class="query-btn" onclick="executeQuery('6')">6️⃣ GMC Vehicle Drivers</button>
    <button class="query-btn" onclick="executeQuery('7')">7️⃣ High-Value Invoices</button>
    <button class="query-btn" onclick="executeQuery('8')">8️⃣ Customer Summary</button>
    <button class="query-btn" onclick="executeQuery('9')">9️⃣ High-Mileage Drivers</button>
  </div>

  <!-- Query 3 Options -->
  <div id="query3-options" class="query3-options">
    <label for="query3-groupby">Group By:</label>
    <select id="query3-groupby" onchange="updateQuery3GroupBy(this.value)">
      <option value="none">None (default)</option>
      <option value="driver">Driver</option>
      <option value="vehicle">Vehicle</option>
    </select>
  </div>

  <!-- Query 4 Options (Newly Added) -->
  <div id="query4-options" class="query4-options">
    <label for="query4-groupby">Group By:</label>
    <select id="query4-groupby" onchange="updateQuery4GroupBy(this.value)">
      <option value="none">None (default)</option>
      <option value="date">Date</option>
      <option value="driver">Driver</option>
      <option value="location">Location</option>
      <option value="vehicle">Vehicle</option>
    </select>
  </div>

  <form id="queryForm" method="POST">
    <input type="hidden" id="selectedQuery" name="query_type" value="">
    <input type="hidden" id="query3GroupBy" name="query3_group_by" value="none">
    <input type="hidden" id="query4GroupBy" name="query4_group_by" value="none">
    <input type="hidden" name="executeQuery" value="1">
  </form>
</div>

<!-- =====================================================
     TRANSACTION EXECUTION PANEL
     ===================================================== -->
<div id="transactionPanel">
  <h2>💾 Transaction Execution</h2>

  <div class="section-title">Transaction 10: Update Mission Details</div>
  <form method="POST">
    <input type="hidden" name="executeTransaction" value="1">
    <input type="hidden" name="transaction_type" value="update_mission">

    <div class="form-group">
      <label for="update_mission_id">Mission ID *</label>
      <input type="number" id="update_mission_id" name="mission_id" required min="1">
    </div>

    <div class="form-group">
      <label for="update_start_datetime">Actual Start Date/Time *</label>
      <input type="datetime-local" id="update_start_datetime" name="actual_start_datetime" required>
    </div>

    <div class="form-group">
      <label for="update_end_datetime">Actual End Date/Time *</label>
      <input type="datetime-local" id="update_end_datetime" name="actual_end_datetime" required>
    </div>

    <div class="form-group">
      <label for="update_odometer_start">Odometer Start (km) *</label>
      <input type="number" id="update_odometer_start" name="odometer_start" required min="0">
    </div>

    <div class="form-group">
      <label for="update_odometer_end">Odometer End (km) *</label>
      <input type="number" id="update_odometer_end" name="odometer_end" required min="0">
    </div>

    <div class="form-group">
      <label for="update_status">Mission Status *</label>
      <select id="update_status" name="mission_status" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <option value="">-- Select Status --</option>
        <option value="S">S - Scheduled</option>
        <option value="C">C - Completed</option>
        <option value="P">P - Pending</option>
      </select>
    </div>

    <button type="submit" class="transaction-btn">✅ Update Mission</button>
  </form>

  <div class="section-title" style="margin-top: 40px;">Transaction 11: Cancel Mission</div>
  <form method="POST">
    <input type="hidden" name="executeTransaction" value="1">
    <input type="hidden" name="transaction_type" value="cancel_mission">

    <div class="form-group">
      <label for="cancel_mission_id">Mission ID *</label>
      <input type="number" id="cancel_mission_id" name="cancel_mission_id" required min="1">
    </div>

    <div class="form-group">
      <label for="cancel_reason">Cancellation Reason *</label>
      <textarea id="cancel_reason" name="cancellation_reason" required placeholder="Enter the reason for cancellation..."></textarea>
    </div>

    <button type="submit" class="transaction-btn">❌ Cancel Mission</button>
  </form>
</div>

<!-- =====================================================
     RESULTS PANEL
     ===================================================== -->
<div id="resultsPanel">
  <?php if ($transactionResult): ?>
    <div class="success"><?php echo htmlspecialchars($transactionResult); ?></div>
  <?php endif; ?>

  <?php if ($queryError): ?>
    <div class="error"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <div id="resultContent">
<?php
// Handle query and transaction execution - Output results inside resultsPanel
if ($isLoggedIn) {
    $servername = "qwc353.encs.concordia.ca";
    $username = $_SESSION['db_user'];
    $password = $_SESSION['db_password'];
    $dbname = "qwc353_4";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if (!$conn->connect_error) {

        // Execute selected query
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executeQuery'])) {
            $query = '';

            switch($_POST['query_type']) {
                // Query #1: List of customers that are businesses (Enterprises or Companies)
                case '1':
                    $query = "SELECT client_id, client_name, client_addr, client_phone, client_type
                    FROM CLIENT WHERE client_type = 'Business' ORDER BY client_name";
                    $title = "Query 1: Business Customers";
                    break;
                // Query #2: List of reservations whose reservation number is greater than 1.
                case '2':
                    $query = "SELECT res_id, client_id, reservation_date,
                    CASE WHEN res_status = 'P' THEN 'Pending'
                         WHEN res_status = 'C' THEN 'Completed'
                         WHEN res_status = 'X' THEN 'Cancelled'
                    END as res_status,
                    requested_vehicle_type, expected_duration, appointment_datetime FROM RESERVATION WHERE res_id > 1 ORDER BY res_id";
                    $title = "Query 2: Reservations (res_id > 1)";
                    break;
                // Query #3: List of drivers and vehicles having participated in at least one mission.
                case '3':
                    $groupBy = $_POST['query3_group_by'] ?? 'none';

                    if ($groupBy === 'driver') {
                        $query = "SELECT d.driver_id,
                        COALESCE(d.driver_first_name, 'N/A') as driver_first_name,
                        COALESCE(d.driver_last_name, 'N/A') as driver_last_name,
                        COUNT(DISTINCT m.mission_id) as mission_count
                        FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id
                        GROUP BY d.driver_id, d.driver_first_name, d.driver_last_name
                        ORDER BY d.driver_id";
                        $title = "Query 3: Active Drivers (Grouped by Driver)";
                        $hideColumns = array('vehicle_id', 'vehicle_type', 'vehicle_brand');
                    } elseif ($groupBy === 'vehicle') {
                        $query = "SELECT v.vehicle_id, v.vehicle_type, v.vehicle_brand,
                        COUNT(DISTINCT m.mission_id) as mission_count
                        FROM VEHICLE v JOIN MISSION m ON v.vehicle_id = m.vehicle_id
                        GROUP BY v.vehicle_id, v.vehicle_type, v.vehicle_brand
                        ORDER BY v.vehicle_id";
                        $title = "Query 3: Active Vehicles (Grouped by Vehicle)";
                        $hideColumns = array('driver_id', 'driver_first_name', 'driver_last_name');
                    } else {
                        $query = "SELECT DISTINCT d.driver_id,
                        COALESCE(d.driver_first_name, 'N/A') as driver_first_name,
                        COALESCE(d.driver_last_name, 'N/A') as driver_last_name,
                        v.vehicle_id, v.vehicle_type, v.vehicle_brand
                        FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id
                        JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id ORDER BY d.driver_id, v.vehicle_id";
                        $title = "Query 3: Active Drivers and Vehicles in Missions";
                        $hideColumns = array();
                    }
                    break;
                //Query #4: List of missions between February 11, 2026 and February 18, 2026 as well as the drivers and vehicles participating in these missions.
                case '4':
                    $groupBy = $_POST['query4_group_by'] ?? 'none';

                    if ($groupBy === 'date') {
                        $query = "SELECT DATE(m.appointment_datetime) as appointment_date,
                        COUNT(DISTINCT m.mission_id) as mission_count
                        FROM MISSION m
                        WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-11' AND '2026-02-18'
                        GROUP BY DATE(m.appointment_datetime)
                        ORDER BY appointment_date";
                        $title = "Query 4: Missions Between Feb 11-18, 2026 (Grouped by Date)";
                        $hideColumns = array();
                    } elseif ($groupBy === 'driver') {
                        $query = "SELECT d.driver_id, COALESCE(d.driver_first_name, 'N/A') as driver_first_name,
                        COALESCE(d.driver_last_name, 'N/A') as driver_last_name,
                        COUNT(DISTINCT m.mission_id) as mission_count
                        FROM MISSION m JOIN DRIVER d ON m.driver_id = d.driver_id
                        WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-11' AND '2026-02-18'
                        GROUP BY d.driver_id, d.driver_first_name, d.driver_last_name
                        ORDER BY d.driver_id";
                        $title = "Query 4: Missions Between Feb 11-18, 2026 (Grouped by Driver)";
                        $hideColumns = array();
                    } elseif ($groupBy === 'location') {
                        $query = "SELECT m.rendezvous_location,
                        COUNT(DISTINCT m.mission_id) as mission_count
                        FROM MISSION m
                        WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-11' AND '2026-02-18'
                        GROUP BY m.rendezvous_location
                        ORDER BY m.rendezvous_location";
                        $title = "Query 4: Missions Between Feb 11-18, 2026 (Grouped by Location)";
                        $hideColumns = array();
                    } elseif ($groupBy === 'vehicle') {
                        $query = "SELECT v.vehicle_id, v.vehicle_type, v.vehicle_brand,
                        COUNT(DISTINCT m.mission_id) as mission_count
                        FROM MISSION m JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id
                        WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-11' AND '2026-02-18'
                        GROUP BY v.vehicle_id, v.vehicle_type, v.vehicle_brand
                        ORDER BY v.vehicle_id";
                        $title = "Query 4: Missions Between Feb 11-18, 2026 (Grouped by Vehicle)";
                        $hideColumns = array();
                    } else {
                        $query = "SELECT m.mission_id, DATE(m.appointment_datetime) as appointment_date, TIME(m.appointment_datetime) as appointment_time,
                        CASE WHEN m.mission_status = 'S' THEN 'Started'
                             WHEN m.mission_status = 'C' THEN 'Completed'
                             WHEN m.mission_status = 'P' THEN 'Pending'
                        END as mission_status,
                        d.driver_id, COALESCE(d.driver_first_name, 'N/A') as driver_first_name,
                        COALESCE(d.driver_last_name, 'N/A') as driver_last_name,
                        d.driver_licence_type, v.vehicle_id, v.vehicle_type,
                        v.vehicle_brand, m.rendezvous_location, m.expected_duration
                        FROM MISSION m JOIN DRIVER d ON m.driver_id = d.driver_id
                        JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id
                        WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-11' AND '2026-02-18'
                        ORDER BY m.mission_id";
                        $title = "Query 4: Missions Between Feb 11-18, 2026";
                        $hideColumns = array();
                    }
                    break;
                // Query #5: The list of customers who have not paid their invoices.
                case '5':
                    $query = "SELECT DISTINCT c.client_id, c.client_name, c.client_addr,
                    c.client_phone, c.client_type, i.invoice_id, i.invoice_date,
                    CASE WHEN p.pay_status = 'C' THEN 'Completed'
                         WHEN p.pay_status = 'P' THEN 'Pending'
                    END as pay_status,
                    CONCAT('$', FORMAT(p.amount, 2)) as amount
                    FROM CLIENT c JOIN INVOICE i ON c.client_id = i.client_id
                    JOIN PAYMENT p ON i.invoice_id = p.invoice_id WHERE p.pay_status = 'P'
                    ORDER BY c.client_id, i.invoice_id";
                    $title = "Query 5: Customers with Unpaid Invoices";
                    break;
                // Query #6: List of drivers who have driven 'GMC' brand vehicles.
                case '6':
                    $query = "SELECT DISTINCT d.driver_id,
                    COALESCE(d.driver_first_name, 'N/A') as driver_first_name,
                    COALESCE(d.driver_last_name, 'N/A') as driver_last_name, v.vehicle_id, v.vehicle_brand
                    FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id
                    JOIN VEHICLE v ON m.vehicle_id = v.vehicle_id WHERE v.vehicle_brand = 'GMC'
                    ORDER BY d.driver_id";
                    $title = "Query 6: Drivers Who Drove GMC Vehicles";
                    break;
                // Query #7: Which customers have invoices greater than 1000 $?
                case '7':
                    $query = "SELECT c.client_id, c.client_name, c.client_addr, c.client_phone, i.invoice_id, i.invoice_date,
                    CONCAT('$', FORMAT(SUM(il.rental_cost), 2)) as total_rental_cost
                    FROM CLIENT c JOIN INVOICE i ON c.client_id = i.client_id
                    JOIN INVOICE_LINE il ON i.invoice_id = il.invoice_id
                    GROUP BY c.client_id, c.client_name, c.client_addr, c.client_phone, i.invoice_id, i.invoice_date
                    HAVING SUM(il.rental_cost) > 1000.00 ORDER BY SUM(il.rental_cost) DESC, c.client_id";
                    $title = "Query 7: Customers with Invoices > $1000";
                    break;
                // Query #8: List of customers with their number of associated invoices.
                case '8':
                    $query = "SELECT c.client_id, c.client_name, c.client_addr, c.client_phone, c.client_type,
                    COUNT(i.invoice_id) as invoice_count, CONCAT('$',
                    FORMAT(COALESCE(SUM(il.rental_cost), 0), 2)) as total_rental_cost
                    FROM CLIENT c LEFT JOIN INVOICE i ON c.client_id = i.client_id
                    LEFT JOIN INVOICE_LINE il ON i.invoice_id = il.invoice_id
                    GROUP BY c.client_id, c.client_name, c.client_addr, c.client_phone, c.client_type
                    ORDER BY invoice_count DESC, c.client_id";
                    $title = "Query 8: Customer Invoice Summary";
                    break;
                // Query #9: What are the last names and first names of the drivers who have a mission between the following dates: February 1, 2026 and March 30, 2026 whose mileage (number of kilometers traveled) is more than 7000 km?
                case '9':
                    $query = "SELECT d.driver_id, COALESCE(d.driver_first_name, 'N/A') as driver_first_name,
                    COALESCE(d.driver_last_name, 'N/A') as driver_last_name,
                    MAX((m.odometer_end - m.odometer_start)) as max_kilometers_traveled
                    FROM DRIVER d JOIN MISSION m ON d.driver_id = m.driver_id
                    WHERE DATE(m.appointment_datetime) BETWEEN '2026-02-01' AND '2026-03-30'
                    AND (m.odometer_end - m.odometer_start) > 7000 GROUP BY d.driver_id, d.driver_first_name, d.driver_last_name
                    ORDER BY max_kilometers_traveled DESC";
                    $title = "Query 9: High-Mileage Drivers (>7000km, Feb-Mar 2026)";
                    break;
            }

            if ($query) {
                $result = $conn->query($query);

                if ($result) {
                    echo "<script>showPanel('resultsPanel');</script>";
                    echo "<h3>" . htmlspecialchars($title) . "</h3>";

                    // Show Query 3 dropdown if this is Query 3
                    if ($_POST['query_type'] === '3') {
                        echo "<script>document.getElementById('query3-options').classList.add('show');</script>";
                    }

                    // Show Query 4 dropdown if this is Query 4
                    if ($_POST['query_type'] === '4') {
                        echo "<script>document.getElementById('query4-options').classList.add('show');</script>";
                    }

                    if ($result->num_rows > 0) {
                        echo "<table>";

                        // Display headers
                        $fields = $result->fetch_fields();
                        $fieldNames = array();
                        echo "<tr>";
                        foreach ($fields as $field) {
                            // Skip columns that should be hidden
                            if (isset($hideColumns) && in_array($field->name, $hideColumns)) {
                                continue;
                            }
                            $fieldNames[] = $field->name;
                            echo "<th>" . htmlspecialchars($field->name) . "</th>";
                        }
                        echo "</tr>";

                        // Display data
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            foreach ($fieldNames as $fieldName) {
                                echo "<td>" . htmlspecialchars($row[$fieldName]) . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p class='no-results'>No results found for this query.</p>";
                    }
                } else {
                    echo "<script>showPanel('resultsPanel');</script>";
                    echo "<div class='error'>Query Error: " . htmlspecialchars($conn->error) . "</div>";
                }
            }
        }

        // Execute two transactions
        // Query #10: Write a transaction to update details of a mission, given the mission ID. (We expect the end date to change accordingly).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['executeTransaction'])) {
            $transactionType = $_POST['transaction_type'] ?? '';

            if ($transactionType === 'update_mission') {
                $missionId = intval($_POST['mission_id']);
                $actualStart = $_POST['actual_start_datetime'];
                $actualEnd = $_POST['actual_end_datetime'];
                $odometerStart = intval($_POST['odometer_start']);
                $odometerEnd = intval($_POST['odometer_end']);
                $missionStatus = $_POST['mission_status'];

                // Validate inputs
                if (empty($missionId) || empty($actualStart) || empty($actualEnd) || empty($odometerStart) || empty($odometerEnd) || empty($missionStatus)) {
                    echo "<script>showPanel('resultsPanel');</script>";
                    echo "<div class='error'>All fields are required for Update Mission transaction.</div>";
                } else {
                    // Convert datetime-local to MySQL format
                    $actualStart = str_replace('T', ' ', $actualStart);
                    $actualEnd = str_replace('T', ' ', $actualEnd);

                    $stmt = $conn->prepare("CALL update_mission_details(?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issiis", $missionId, $actualStart, $actualEnd, $odometerStart, $odometerEnd, $missionStatus);

                    if ($stmt->execute()) {
                        echo "<script>showPanel('resultsPanel');</script>";
                        echo "<div class='success'>✅ Mission " . $missionId . " updated successfully!</div>";

                        // Display updated mission details
                        $verifyQuery = "SELECT * FROM MISSION WHERE mission_id = " . $missionId;
                        $verifyResult = $conn->query($verifyQuery);
                        if ($verifyResult && $verifyResult->num_rows > 0) {
                            echo "<h4>Updated Mission Details:</h4>";
                            echo "<table>";
                            $fields = $verifyResult->fetch_fields();
                            echo "<tr>";
                            foreach ($fields as $field) {
                                echo "<th>" . htmlspecialchars($field->name) . "</th>";
                            }
                            echo "</tr>";
                            while($row = $verifyResult->fetch_assoc()) {
                                echo "<tr>";
                                foreach ($row as $cell) {
                                    echo "<td>" . htmlspecialchars($cell) . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</table>";
                        }
                    } else {
                        echo "<script>showPanel('resultsPanel');</script>";
                        echo "<div class='error'>Error executing Update Mission: " . htmlspecialchars($stmt->error) . "</div>";
                    }
                    $stmt->close();
                }
            }

            // Query #11:  Write a transaction to cancel a mission or part of a mission.
            elseif ($transactionType === 'cancel_mission') {
                $missionId = intval($_POST['cancel_mission_id']);
                $reason = $_POST['cancellation_reason'];

                if (empty($missionId) || empty($reason)) {
                    echo "<script>showPanel('resultsPanel');</script>";
                    echo "<div class='error'>All fields are required for Cancel Mission transaction.</div>";
                } else {
                    $stmt = $conn->prepare("CALL cancel_mission(?, ?)");
                    $stmt->bind_param("is", $missionId, $reason);

                    if ($stmt->execute()) {
                        // Get the result message
                        $resultSet = $stmt->get_result();
                        $resultRow = $resultSet->fetch_assoc();
                        $resultMessage = $resultRow['result'] ?? 'Mission cancelled successfully';

                        echo "<script>showPanel('resultsPanel');</script>";
                        echo "<div class='success'>✅ " . htmlspecialchars($resultMessage) . "</div>";

                        // Display cancelled mission details
                        $verifyQuery = "SELECT * FROM MISSION WHERE mission_id = " . $missionId;
                        $verifyResult = $conn->query($verifyQuery);
                        if ($verifyResult && $verifyResult->num_rows > 0) {
                            echo "<h4>Cancelled Mission Details:</h4>";
                            echo "<table>";
                            $fields = $verifyResult->fetch_fields();
                            echo "<tr>";
                            foreach ($fields as $field) {
                                echo "<th>" . htmlspecialchars($field->name) . "</th>";
                            }
                            echo "</tr>";
                            while($row = $verifyResult->fetch_assoc()) {
                                echo "<tr>";
                                foreach ($row as $cell) {
                                    echo "<td>" . htmlspecialchars($cell) . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</table>";
                        }
                    } else {
                        echo "<script>showPanel('resultsPanel');</script>";
                        echo "<div class='error'>Error executing Cancel Mission: " . htmlspecialchars($stmt->error) . "</div>";
                    }
                    $stmt->close();
                }
            }
        }

        $conn->close();
    }
}
?>
  </div>
</div>

<!-- =====================================================
     VIEW ALL TABLES PANEL

     ===================================================== -->
<div id="dbContent">

<?php
// MySQL Database Connection using session credentials
$servername = "qwc353.encs.concordia.ca";
$username = $_SESSION['db_user'];
$password = $_SESSION['db_password'];
$dbname = "qwc353_4";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo '<p class="error">Invalid credentials. Please <a href="?logout=1">logout</a> and try again.</p>';
} else {

echo "<h2>Database Tables</h2>";

// Get all tables from the database
$result = $conn->query("SHOW TABLES");

if ($result->num_rows > 0) {
    while($row = $result->fetch_row()) {
        $tableName = $row[0];
        echo "<h3>Table: " . htmlspecialchars($tableName) . "</h3>";

        // Get all data from each table
        $tableQuery = "SELECT * FROM " . $tableName;
        $tableResult = $conn->query($tableQuery);

        if ($tableResult->num_rows > 0) {
            echo "<table>";

            // Display table headers
            $fields = $tableResult->fetch_fields();
            echo "<tr>";
            foreach ($fields as $field) {
                echo "<th>" . htmlspecialchars($field->name) . "</th>";
            }
            echo "</tr>";

            // Display table data
            while($tableRow = $tableResult->fetch_assoc()) {
                echo "<tr>";
                foreach ($tableRow as $cell) {
                    echo "<td>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='no-results'>No data found in this table.</p>";
        }
    }
} else {
    echo "<p class='no-results'>No tables found in the database.</p>";
}

$conn->close();
}
?>

</div>

<?php endif; ?>