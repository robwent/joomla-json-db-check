# Joomla JSON Database Check

Checks Joomla 'params', 'rules' and 'attribs' fields for invalid JSON data.

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
6. If the site still has errors, select the columns you want to check by checking the checkboxes and click on the 'Check For All Invalid Values' button to check each field.
7. Check each error warning in a JSON validator and either manually fix it or contact the relevant extensions developer.
8. Delete this script from your hosting account.

More detail and some screenshots can be seen in this [blog post](https://robertwent.com/blog/joomla/102-fixing-json-data-errors-after-updating-to-joomla-3-3-6)

## False Positives

The script has been updated to use the same check that Joomla does to see if the data should be decoded from JSON.

It should only show issues that will cause Joomla to show an error.

If you see an error in the script output but Joomla is working without any problems, please report it in [issues](https://github.com/robwent/joomla-json-db-check/issues).
