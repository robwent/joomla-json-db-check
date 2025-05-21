<?php
/**
 * JSON Database Field Checker and Fixer
 *
 * This script checks and fixes invalid JSON in database columns.
 * - Fixes empty JSON fields
 * - Identifies malformed JSON data
 */

// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Initialize variables
$conn = null;
$connected = false;
$message = '';
$dbDetails = [];
$fixResults = [];
$checkResults = [];

// Get database details from either POST or session
session_start();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Get database details
	$dbDetails = [
		'host' => $_POST['db_host'] ?? 'localhost',
		'username' => $_POST['db_username'] ?? '',
		'password' => $_POST['db_password'] ?? '',
		'database' => $_POST['db_name'] ?? '',
		'prefix' => $_POST['db_prefix'] ?? '',
	];

	// Save to session
	$_SESSION['db_details'] = $dbDetails;

	// Try to connect
	try {
		$conn = new mysqli(
			$dbDetails['host'],
			$dbDetails['username'],
			$dbDetails['password'],
			$dbDetails['database']
		);

		if ($conn->connect_error) {
			throw new Exception("Connection failed: " . $conn->connect_error);
		}

		$connected = true;

		// Only show connection message on initial connect
		if (isset($_POST['connect']) && !isset($_POST['fix_empty']) && !isset($_POST['check_json'])) {
			$message = "Connected successfully to database: {$dbDetails['database']}";
		}
	} catch (Exception $e) {
		$message = "Error: " . $e->getMessage();
		$connected = false;
	}
} elseif (isset($_SESSION['db_details'])) {
	// Restore from session if available
	$dbDetails = $_SESSION['db_details'];

	// Try to reconnect
	try {
		$conn = new mysqli(
			$dbDetails['host'],
			$dbDetails['username'],
			$dbDetails['password'],
			$dbDetails['database']
		);

		if ($conn->connect_error) {
			throw new Exception("Connection failed: " . $conn->connect_error);
		}

		$connected = true;
	} catch (Exception $e) {
		$message = "Error: " . $e->getMessage();
		$connected = false;
	}
}

// Helper functions for JSON validation
function is_trying_to_be_json($data) {
	$data = trim((string)$data);
	return ((substr($data, 0, 1) === '{') || (substr($data, -1, 1) === '}')) ? true : false;
}

function is_valid_json($string) {
	json_decode($string);
	return (json_last_error() === JSON_ERROR_NONE);
}

// Fix empty parameters
if (isset($_POST['fix_empty']) && $connected) {
	$columns = $_POST['columns'] ?? [];

	if (!empty($columns)) {
		// Build the column list for the SQL
		$columnClauses = [];
		foreach ($columns as $column) {
			$columnClauses[] = "COLUMN_NAME = '" . $conn->real_escape_string($column) . "'";
		}
		$columnString = implode(' OR ', $columnClauses);

		// Find tables with the specified columns
		$query = "SELECT TABLE_NAME, COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE ($columnString) 
                  AND TABLE_SCHEMA = '{$dbDetails['database']}'";

		$result = $conn->query($query);

		if ($result && $result->num_rows > 0) {
			while ($row = $result->fetch_object()) {
				$tableName = $row->TABLE_NAME;
				$columnName = $row->COLUMN_NAME;

				// Apply prefix filter if specified
				if (!empty($dbDetails['prefix']) && strpos($tableName, $dbDetails['prefix']) !== 0) {
					continue;
				}

				// Update query to fix empty JSON
				$updateQuery = "UPDATE `$tableName` 
                                SET `$columnName` = '{}' 
                                WHERE `$columnName` = '' 
                                   OR `$columnName` = '{\"\"}'
                                   OR `$columnName` = '{\\\"\\\"}'";

				$conn->query($updateQuery);
				$affectedRows = $conn->affected_rows;

				if ($affectedRows > 0) {
					$fixResults[] = "Table: $tableName, Column: $columnName - $affectedRows rows fixed";
				}
			}
		}

		if (empty($fixResults)) {
			$fixResults[] = "No empty parameters found that needed fixing.";
		}

		$message = "Empty JSON fields check completed.";
	} else {
		$message = "Please select at least one column to check!";
	}
}

// Check for invalid JSON syntax
if (isset($_POST['check_json']) && $connected) {
	$columns = $_POST['columns'] ?? [];

	if (!empty($columns)) {
		// Build the column list for the SQL
		$columnClauses = [];
		foreach ($columns as $column) {
			$columnClauses[] = "COLUMN_NAME = '" . $conn->real_escape_string($column) . "'";
		}
		$columnString = implode(' OR ', $columnClauses);

		// Find tables with the specified columns
		$query = "SELECT TABLE_NAME, COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE ($columnString) 
                  AND TABLE_SCHEMA = '{$dbDetails['database']}'";

		$result = $conn->query($query);

		if ($result && $result->num_rows > 0) {
			while ($row = $result->fetch_object()) {
				$tableName = $row->TABLE_NAME;
				$columnName = $row->COLUMN_NAME;

				// Apply prefix filter if specified
				if (!empty($dbDetails['prefix']) && strpos($tableName, $dbDetails['prefix']) !== 0) {
					continue;
				}

				// Query to find non-empty JSON fields
				$checkQuery = "SELECT *, `$columnName` AS json_content FROM `$tableName` WHERE `$columnName` != '{}'";
				$checkResult = $conn->query($checkQuery);

				if ($checkResult && $checkResult->num_rows > 0) {
					$invalidCount = 0;
					$tableResult = [];

					while ($dataRow = $checkResult->fetch_assoc()) {
						$jsonContent = $dataRow['json_content'];

						if (!is_valid_json($jsonContent) && is_trying_to_be_json($jsonContent)) {
							// Get the primary key if possible
							$primaryKeyQuery = "SELECT COLUMN_NAME 
                                              FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                                              WHERE TABLE_SCHEMA = '{$dbDetails['database']}' 
                                              AND TABLE_NAME = '$tableName' 
                                              AND CONSTRAINT_NAME = 'PRIMARY'";

							$primaryKeyResult = $conn->query($primaryKeyQuery);
							$primaryKeyName = ($primaryKeyResult && $primaryKeyResult->num_rows > 0)
								? $primaryKeyResult->fetch_object()->COLUMN_NAME
								: null;

							$rowIdentifier = $primaryKeyName && isset($dataRow[$primaryKeyName])
								? "ID {$dataRow[$primaryKeyName]}"
								: "Row " . (++$invalidCount);

							$error = json_last_error_msg();
							$tableResult[] = "$rowIdentifier - Error: $error";
						}
					}

					if (!empty($tableResult)) {
						$checkResults[$tableName . '.' . $columnName] = $tableResult;
					}
				}
			}
		}

		if (empty($checkResults)) {
			$checkResults['message'] = "No invalid JSON syntax found in the selected columns.";
		}

		$message = "JSON syntax check completed.";
	} else {
		$message = "Please select at least one column to check!";
	}
}

// Close connection
if ($conn) {
	$conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>JSON Database Checker</title>
	<style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3, h4 {
            color: #444;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            display: inline-block;
            background-color: #0078e7;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #0069c8;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .alert-danger {
            background-color: #f2dede;
            color: #a94442;
        }
        .checkbox-group {
            margin: 10px 0;
        }
        .checkbox-group label {
            display: inline-block;
            margin-right: 15px;
            font-weight: normal;
        }
        .results {
            border-left: 4px solid #0078e7;
            padding-left: 15px;
            margin-left: 10px;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
	</style>
</head>
<body>
<div class="container">
	<h1>JSON Database Field Checker</h1>
	<p>This tool checks and fixes JSON fields in your database.</p>

	<?php if (!empty($message)): ?>
		<div class="alert <?php echo strpos($message, 'Error') === false ? 'alert-success' : 'alert-danger'; ?>">
			<?php echo $message; ?>
		</div>
	<?php endif; ?>

	<div class="card">
		<h2>Database Connection</h2>
		<form method="post" action="">
			<div class="form-group">
				<label for="db_host">Host:</label>
				<input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($dbDetails['host'] ?? 'localhost'); ?>" required>
			</div>
			<div class="form-group">
				<label for="db_username">Username:</label>
				<input type="text" id="db_username" name="db_username" value="<?php echo htmlspecialchars($dbDetails['username'] ?? ''); ?>" required>
			</div>
			<div class="form-group">
				<label for="db_password">Password:</label>
				<input type="password" id="db_password" name="db_password" value="<?php echo htmlspecialchars($dbDetails['password'] ?? ''); ?>">
			</div>
			<div class="form-group">
				<label for="db_name">Database Name:</label>
				<input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($dbDetails['database'] ?? ''); ?>" required>
			</div>
			<div class="form-group">
				<label for="db_prefix">Table Prefix (optional, filters tables):</label>
				<input type="text" id="db_prefix" name="db_prefix" value="<?php echo htmlspecialchars($dbDetails['prefix'] ?? ''); ?>">
			</div>
			<button type="submit" name="connect" class="btn">Connect</button>
		</form>
	</div>

	<?php if ($connected): ?>
		<div class="card">
			<h2>JSON Operations</h2>
			<form method="post" action="">
				<!-- Pass database credentials in hidden fields -->
				<input type="hidden" name="db_host" value="<?php echo htmlspecialchars($dbDetails['host']); ?>">
				<input type="hidden" name="db_username" value="<?php echo htmlspecialchars($dbDetails['username']); ?>">
				<input type="hidden" name="db_password" value="<?php echo htmlspecialchars($dbDetails['password']); ?>">
				<input type="hidden" name="db_name" value="<?php echo htmlspecialchars($dbDetails['database']); ?>">
				<input type="hidden" name="db_prefix" value="<?php echo htmlspecialchars($dbDetails['prefix']); ?>">

				<h3>Columns to check:</h3>
				<div class="checkbox-group">
					<label>
						<input type="checkbox" name="columns[]" value="params" <?php echo isset($_POST['columns']) && in_array('params', $_POST['columns']) ? 'checked' : ''; ?>>
						params
					</label>
					<label>
						<input type="checkbox" name="columns[]" value="rules" <?php echo isset($_POST['columns']) && in_array('rules', $_POST['columns']) ? 'checked' : ''; ?>>
						rules
					</label>
					<label>
						<input type="checkbox" name="columns[]" value="attribs" <?php echo isset($_POST['columns']) && in_array('attribs', $_POST['columns']) ? 'checked' : ''; ?>>
						attribs
					</label>
					<label>
						<input type="checkbox" name="columns[]" value="options" <?php echo isset($_POST['columns']) && in_array('options', $_POST['columns']) ? 'checked' : ''; ?>>
						options
					</label>
					<label>
						<input type="checkbox" name="columns[]" value="metadata" <?php echo isset($_POST['columns']) && in_array('metadata', $_POST['columns']) ? 'checked' : ''; ?>>
						metadata
					</label>
				</div>

				<div class="form-group">
					<button type="submit" name="fix_empty" class="btn">Fix Empty JSON Fields</button>
					<button type="submit" name="check_json" class="btn">Check for Invalid JSON</button>
				</div>
			</form>

			<?php if (!empty($fixResults)): ?>
				<div class="results">
					<h3>Fix Results</h3>
					<ul>
						<?php foreach($fixResults as $result): ?>
							<li><?php echo htmlspecialchars($result); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if (!empty($checkResults)): ?>
				<div class="results">
					<h3>Check Results</h3>
					<?php if (isset($checkResults['message'])): ?>
						<p><?php echo htmlspecialchars($checkResults['message']); ?></p>
					<?php else: ?>
						<?php foreach($checkResults as $tableColumn => $results): ?>
							<h4><?php echo htmlspecialchars($tableColumn); ?></h4>
							<ul>
								<?php foreach($results as $result): ?>
									<li><?php echo htmlspecialchars($result); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endforeach; ?>
						<p>To fix invalid JSON, you can use <a href="https://jsonlint.com/" target="_blank">JSONLint</a> to validate and correct the syntax.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="card">
			<h2>SQL Queries</h2>
			<p>If you prefer to run SQL queries directly in phpMyAdmin, here are the queries used:</p>

			<h3>Find tables with JSON columns:</h3>
			<pre>SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE (COLUMN_NAME = 'params' OR COLUMN_NAME = 'rules' OR COLUMN_NAME = 'attribs')
AND TABLE_SCHEMA = '<?php echo htmlspecialchars($dbDetails['database'] ?? 'your_database_name'); ?>';</pre>

			<h3>Fix empty JSON in a specific table and column:</h3>
			<pre>UPDATE `table_name`
SET `column_name` = '{}'
WHERE `column_name` = ''
   OR `column_name` = '{\"\"}'
   OR `column_name` = '{\\\"\\\"}';</pre>

			<h3>Find non-empty JSON fields for inspection:</h3>
			<pre>SELECT * FROM `table_name` WHERE `column_name` != '{}';</pre>
		</div>
	<?php endif; ?>
</div>
</body>
</html>
