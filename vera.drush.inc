<?php

/**
 * Implements hook_drush_help().
 */
function vera_drush_help($section) {
  return dt('It is my very favorite custom Drupal profile helper suite.');
}

/**
 * Implements hook_drush_command().
 */
function vera_drush_command() {
  $items = array();

  $items['vera'] = array(
    'description' => 'General non-project-specific functionality.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'arguments' => array(
      'coverage' => 'Check test coverage against Pivotal Tracker.',
      'generate' => 'Generate a starting profile from the latest version of Drupal.',
      'nuke' => 'Destroy and rebuild the existing site from the current installation profile.',
      'test' => 'Run tests against the project install profile.',
    ),
    'aliases' => array('v'),
  );

  return $items;
}

/**
 * Implements drush_vera().
 *
 * @see \Vera\Command
 */
function drush_vera($command) {
  require 'vendor/autoload.php';
  $command = '\Vera\Command\\' . ucfirst($command);

  if (class_exists($command)) {
    new $command();
  } else {
    drush_log('Vera needs oxygen around her to fire.', 'error');
  }
}
