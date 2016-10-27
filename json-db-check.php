<?php
/**
 * Turn on outputbuffering for servers that have it disabled.
 */
ob_start();
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Joomla json Check</title>
		<style>
			.btn {
				/* Structure */
				display: inline-block;
				zoom: 1;
				line-height: normal;
				white-space: nowrap;
				vertical-align: middle;
				text-align: center;
				cursor: pointer;
				-webkit-user-drag: none;
				-webkit-user-select: none;
				-moz-user-select: none;
				-ms-user-select: none;
				user-select: none;
				-webkit-box-sizing: border-box;
				-moz-box-sizing: border-box;
				box-sizing: border-box;
				font-family: inherit;
				font-size: 100%;
				padding: 0.5em 1em;
				border: 1px solid #999;  /*IE 6/7/8*/
				border: none rgba(0, 0, 0, 0);  /*IE9 + everything else*/
				background-color: rgb(0, 120, 231);
				color: #fff;
				text-decoration: none;
				border-radius: 2px;
			}
		</style>
	</head>
	<body>
		<?php
		//Initiate Joomla so we can use it's functions
		/**
		 * Constant that is checked in included files to prevent direct access.
		 * define() is used in the installation folder rather than "const" to not error for PHP 5.2 and lower
		 */
		define('_JEXEC', 1);

		if (file_exists(__DIR__ . '/defines.php'))
		{
			include_once __DIR__ . '/defines.php';
		}

		if (!defined('_JDEFINES'))
		{
			define('JPATH_BASE', __DIR__);
			require_once JPATH_BASE . '/includes/defines.php';
		}

		require_once JPATH_BASE . '/includes/framework.php';

		// Instantiate the application.
		$app    = JFactory::getApplication('site');
		$db     = JFactory::getDbo();
		$config = JFactory::getConfig();

		$jinput    = $app->input;
		$fullcheck = $jinput->get('fullcheck', 0, 'INT');

		function is_json()
		{
			call_user_func_array('json_decode', func_get_args());

			return (json_last_error() === JSON_ERROR_NONE);
		}

		//We use this for both checks
		$query = $db->getQuery(true)
			->select('TABLE_NAME,COLUMN_NAME')
			->from('INFORMATION_SCHEMA.COLUMNS')
			->where('COLUMN_NAME = \'params\' OR COLUMN_NAME = \'rules\'')
			->andWhere('TABLE_SCHEMA = \'' . $config->get('db') . '\'');

		$db->setQuery($query);
		$results = $db->loadObjectList();
		?>
		<?php if ($fullcheck == 0) : ?>
			<h4>Checking for Invalid Empty Parameters</h4>
			<?php
			if ($results)
			{
				foreach ($results as $result)
				{
					echo "Checking table: {$result->TABLE_NAME}, column {$result->COLUMN_NAME}<br>";
					$query = $db->getQuery(true)
						->update($result->TABLE_NAME)
						->set($result->COLUMN_NAME . ' = "{}"')
						->where($result->COLUMN_NAME . ' = "" OR ' . $result->COLUMN_NAME . ' = \'{\"\"}\' OR ' . $result->COLUMN_NAME . ' = \'{\\\\\"\\\\\"}\' ');

					$db->setQuery($query);
					$results = $db->execute();
					$changes = $db->getAffectedRows();

					if ($changes != 0)
					{
						echo $changes . " rows modified.<br>";
					}
				}
			}
			?>
			<h4>Finished checking empty parameters</h4>
			<form>
				<button class="btn" name="fullcheck" value="1">Check For All Invalid Values</button>
			</form>
			<p></p>
			<p><small>(This will not replace any values, you will need to manaully fix them)</small></p>
		<? else : ?>
			<h4>Checking all Params and Rules Entries for Invalid Syntax</h4>
			<?php
			// Check all params for invalid syntax
			if ($results)
			{
				foreach ($results as $result)
				{
					echo "<p>Checking table: {$result->TABLE_NAME}, column {$result->COLUMN_NAME}</p>";
					$query = $db->getQuery(true)
						->select('*')
						->from($result->TABLE_NAME)
						->where($result->COLUMN_NAME . ' != "{}"');

					$db->setQuery($query);

					$results = $db->loadAssocList();

					if ($results)
					{
						foreach ($results as $row)
						{
							if (!is_json($row[$result->COLUMN_NAME]))
							{
								$error = json_last_error_msg();
								$value = reset($row);
								echo "Row {$value[0]} is not valid JSON. Error: ($error)<br>";
								echo "Content: {$row[$result->COLUMN_NAME]}<br><hr>";
							}
						}

					}
				}
			}?>

			<h4>Finished checking invalid parameters</h4>
			<p>Check invalid rules at <a target="_blank" href="http://jsonlint.com/">jsonlint.com</a></p>
			<form>
				<button class="btn" name="fullcheck" value="1">Check Again</button>
			</form>

		<?php endif; ?>
	</body>
</html>
