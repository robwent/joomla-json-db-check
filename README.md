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
8. Delete this script from your hosting account.

More detail and some screenshots can be seen in this [blog post](https://robertwent.com/blog/joomla/102-fixing-json-data-errors-after-updating-to-joomla-3-3-6)

## False Positives

Not everything in these fields may need to be stored in JSON format, it depends on how the information is used. For example, a custom component may use it's own column called 'params' to store information as a serialise array and then validate it using different methods.

In that case, the script would show that the field is not valid but it would not be causing an issue on the site. A serialised array looks like this:

    a:3:{s:6:"action";s:7:"confirm";s:13:"actionbtntext";s:28:"{trans:CONFIRM_SUBSCRIPTION}";s:9:"actionurl";s:19:"{confirm}{/confirm}";}

If running the first check makes the site operational, you can likely forget the detailed check, delete the script and use the site as normal.

If the site still has errors, start by looking at the issues with the core tables and then move on to 3rd party tables which look like they should be storing JSON.
