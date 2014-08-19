<?php

namespace Vera\Audit;

/**
 * URL Alias migration auditing.
 */
class Aliases extends \Vera\Command {

  private $legacyDb;
  private $auditDb;

  private $legacyAliases;
  private $auditAliases;

  function fire() {

    // This command requires a Drupal bootstrap.
    $this->bootstrap();

    // Construct a list of available databases for auditing.
    global $databases;
    foreach ($databases as $key => $database) {
      $options[$key] = $key;
    }

    // Prompt for the legacy database to audit against.
    $this->legacyDb = drush_choice($options, 'Legacy database');
    // Remove the selected option to compare against another database.
    unset($options[$this->legacyDb]);

    // Prompt for the source database if multiples exist.
    if (count($options) >= 2) {
      $this->auditDb = drush_choice($options, 'Current database to audit');
    }
    // Otherwise just choose the last remaining option.
    else {
      $this->auditDb = array_shift($options);
    }

    // Audit the number of aliases found in the legacy database.
    $this->legacyAliases = $this->audit($this->legacyDb);
    drush_log(dt('Found !count aliases in legacy database.', array('!count' => count($this->legacyAliases))), 'success');

    // Audit the number of aliases found in the source database.
    $this->auditAliases = $this->audit($this->auditDb);
    drush_log(dt('Found !count aliases in audited database.', array('!count' => count($this->auditAliases))), 'success');

    // Compare the legacy to the source aliases and see if any are missing.
    if ($diff = array_diff($this->legacyAliases, $this->auditAliases)) {
      drush_log(dt('There are a total of !count unmigrated aliases.', array('!count' => count($diff))), 'error');

      // Print the missing aliases if run in Verbose mode only.
      if (drush_get_context('DRUSH_VERBOSE')) {
        print_r($diff);
      }
    }

  }

  /**
   * Audit the url_aliases table (supports D6 and D7).
   */
  function audit($db) {
    db_set_active($db);

    // Lookup the table schema to support D6 or D7.
    $columns = db_query('SHOW COLUMNS FROM url_alias');
    $schema = $columns->fetchAll();
    $destination = $schema[2]->Field;

    // Pull all aliase using the "Destination" column.
    $aliases = db_select('url_alias', 'u')->fields('u', array($destination))
      ->execute()->fetchAll();

    foreach ($aliases as $alias) {
      $a[] = $alias->$destination;
    }

    // Remove duplicates and any ignored paths.
    $a = array_filter($a, array($this, 'filter'));
    return array_unique($a);
  }

  /**
   * Ignore any file, image or user paths.
   */
  function filter($var) {
    return !(preg_match('#^(files?|images?|users?)\/#i', $var));
  }

}