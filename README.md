# Joomla JSON Database Check

Checks Joomla 'params' and 'rules' fields for invalid JSON data.

## Why?

Joomla 3.6.3 improved validation of JSON data stored in the database (Usually as params for extensions). Unfortunately, this means that after updating, sites with invalid data can can become inaccessible.

The usual error message shown is:

> 0 - Error decoding JSON data: Syntax error

## How To Use

1. Take a backup of your sites database. You use this file at your own risk!
2. Upload json-db-check.php to the root of your site.
3. Browse to the file in any browser.
4. The script will first check for any invalid empty fields and correct them.
5. Check your site to see if the error has gone.
6. If the site still has errors, click on the 'Check For All Invalid Values' button to check each field.
7. Check each error warning in a JSON validator and either manually fix it or contact the relevant extensions developer.
