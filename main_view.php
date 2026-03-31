<!DOCTYPE html>
<html>
<head>
    <title>COMP353 Project - RENTRUCK Database</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>🚚 RENTRUCK Database Management System</h1>

<div class="button-group">
    <?php if ($isLoggedIn): ?>
        <button onclick="showPanel('queryPanel')">📊 Run Queries</button>
        <button onclick="showPanel('transactionPanel')">💾 Execute Transactions</button>
        <button onclick="showPanel('dbContent')">👁️ View All Tables</button>
        <button class="hide-btn" onclick="hideAll()">Hide All</button>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=1" style="text-decoration: none;">
            <button class="hide-btn">🚪 Logout</button>
        </a>
    <?php endif; ?>
</div>

<?php if (!$isLoggedIn): ?>
    <div id="loginPanel">
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
            <button type="submit" name="login" value="1">Login</button>
        </form>
    </div>
<?php else: ?>
    <p style="margin: 10px 0; color: #666;">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['db_user']); ?></strong></p>

    <div id="queryPanel">
        <h2>📊 Query Execution</h2>

        <div class="section-title">SELECT Queries (1-9)</div>
        <div class="query-buttons-row">
            <button class="query-btn" type="button" onclick="executeQuery('1')">1️⃣ Business Customers</button>
            <button class="query-btn" type="button" onclick="executeQuery('2')">2️⃣ Reservations &gt; 1</button>
            <button class="query-btn" type="button" onclick="executeQuery('3')">3️⃣ Active Drivers/Vehicles</button>
            <button class="query-btn" type="button" onclick="executeQuery('4')">4️⃣ Weekly Missions</button>
            <button class="query-btn" type="button" onclick="executeQuery('5')">5️⃣ Unpaid Invoices</button>
            <button class="query-btn" type="button" onclick="executeQuery('6')">6️⃣ GMC Vehicle Drivers</button>
            <button class="query-btn" type="button" onclick="executeQuery('7')">7️⃣ High-Value Invoices</button>
            <button class="query-btn" type="button" onclick="executeQuery('8')">8️⃣ Customer Summary</button>
            <button class="query-btn" type="button" onclick="executeQuery('9')">9️⃣ High-Mileage Drivers</button>
        </div>

        <?php $q3Group = $_POST['query3_group_by'] ?? 'none'; ?>
        <?php $q4Group = $_POST['query4_group_by'] ?? 'none'; ?>

        <div id="query3-options" class="query3-options<?php echo ($selectedQuery === '3') ? ' show' : ''; ?>">
            <label for="query3-groupby">Group By:</label>
            <select id="query3-groupby" onchange="updateQuery3GroupBy(this.value)">
                <option value="none" <?php echo ($q3Group === 'none') ? 'selected' : ''; ?>>None (default)</option>
                <option value="driver" <?php echo ($q3Group === 'driver') ? 'selected' : ''; ?>>Driver</option>
                <option value="vehicle" <?php echo ($q3Group === 'vehicle') ? 'selected' : ''; ?>>Vehicle</option>
            </select>
        </div>

        <div id="query4-options" class="query4-options<?php echo ($selectedQuery === '4') ? ' show' : ''; ?>">
            <label for="query4-groupby">Group By:</label>
            <select id="query4-groupby" onchange="updateQuery4GroupBy(this.value)">
                <option value="none" <?php echo ($q4Group === 'none') ? 'selected' : ''; ?>>None (default)</option>
                <option value="date" <?php echo ($q4Group === 'date') ? 'selected' : ''; ?>>Date</option>
                <option value="driver" <?php echo ($q4Group === 'driver') ? 'selected' : ''; ?>>Driver</option>
                <option value="location" <?php echo ($q4Group === 'location') ? 'selected' : ''; ?>>Location</option>
                <option value="vehicle" <?php echo ($q4Group === 'vehicle') ? 'selected' : ''; ?>>Vehicle</option>
            </select>
        </div>

        <form id="queryForm" method="POST">
            <input type="hidden" id="selectedQuery" name="query_type" value="<?php echo htmlspecialchars($selectedQuery); ?>">
            <input type="hidden" id="query3GroupBy" name="query3_group_by" value="<?php echo htmlspecialchars($q3Group); ?>">
            <input type="hidden" id="query4GroupBy" name="query4_group_by" value="<?php echo htmlspecialchars($q4Group); ?>">
            <input type="hidden" name="executeQuery" value="1">
        </form>
    </div>

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
                <label for="update_status">Mission Status *</label>
                <select id="update_status" name="mission_status" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" onchange="updateMissionFormState()">
                    <option value="">-- Select Status --</option>
                    <option value="S">S - Scheduled</option>
                    <option value="C">C - Completed</option>
                    <option value="P">P - Pending</option>
                </select>
            </div>

            <div id="update_mission_completed_fields">
                <div class="form-group">
                    <label for="update_start_datetime">Actual Start Date/Time *</label>
                    <input type="datetime-local" id="update_start_datetime" name="actual_start_datetime">
                </div>

                <div class="form-group">
                    <label for="update_duration">Duration (hours) *</label>
                    <input type="number" id="update_duration" name="duration_hours" min="1">
                </div>

                <div class="form-group">
                    <label for="update_odometer_start">Odometer Start (km) *</label>
                    <input type="number" id="update_odometer_start" name="odometer_start" min="0">
                </div>

                <div class="form-group">
                    <label for="update_odometer_end">Odometer End (km) *</label>
                    <input type="number" id="update_odometer_end" name="odometer_end" min="0">
                </div>
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

    <div id="resultsPanel">
        <?php if ($transactionResult): ?>
            <div class="<?php echo $transactionIsError ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($transactionResult); ?></div>
        <?php endif; ?>

        <?php if ($queryError): ?>
            <div class="error"><?php echo htmlspecialchars($queryError); ?></div>
        <?php endif; ?>

        <div id="resultContent">
            <?php if ($queryExecuted): ?>
                <h3><?php echo htmlspecialchars($queryTitle); ?></h3>

                <?php if (!empty($queryResult)): ?>
                    <?php
                        $headers = array_keys($queryResult[0]);
                        if (!empty($hideColumns)) {
                                $headers = array_values(array_filter($headers, function ($h) use ($hideColumns) {
                                        return !in_array($h, $hideColumns);
                                }));
                        }
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($headers as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queryResult as $row): ?>
                                <tr>
                                    <?php foreach ($headers as $header): ?>
                                        <td><?php echo htmlspecialchars($row[$header]); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-results">No results found for this query.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($transactionDetailsRows)): ?>
                <h4><?php echo htmlspecialchars($transactionDetailsTitle); ?></h4>
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($transactionDetailsRows[0]) as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactionDetailsRows as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?php echo htmlspecialchars($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div id="dbContent">
        <h2>Database Tables</h2>
        <?php if (!empty($dbTables)): ?>
            <?php foreach ($dbTables as $tableName => $rows): ?>
                <h3><?php echo htmlspecialchars($tableName); ?></h3>
                <?php if (!empty($rows)): ?>
                    <table>
                        <thead>
                            <tr>
                                <?php foreach (array_keys($rows[0]) as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-results">No rows in this table.</p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<script src="script.js"></script>

<?php if ($isLoggedIn && ($queryExecuted || $queryError || $transactionResult)): ?>
    <script>
        showPanel('resultsPanel');
        <?php if ($selectedQuery === '3'): ?>
            document.getElementById('query3-options').classList.add('show');
        <?php endif; ?>
        <?php if ($selectedQuery === '4'): ?>
            document.getElementById('query4-options').classList.add('show');
        <?php endif; ?>
    </script>
<?php endif; ?>

</body>
</html>
