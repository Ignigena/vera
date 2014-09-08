<?php

/**
 * @todo: Docblock.
 */

namespace Vera\Command;

use Symfony\Component\Filesystem\Filesystem;

class Generate extends \Vera\Command {

  function fire() {
    if (!drush_confirm(dt('Generate new site structure in the current working directory?')))
      return;

    if (file_exists('docroot') && !drush_confirm(dt('A Drupal installation already exists. Continue?')))
      return;

    $this->chooseInstallation(file_exists('docroot'));

    $profile = str_replace('Command/Generate.php', 'Profile/vera', __FILE__);
    $fs = new Filesystem();
    $fs->mirror($profile, 'docroot/sites/all/modules/custom/vera');
  }

  function chooseInstallation($exists) {
    if (!drush_get_option('profile')) {
      $makedir = str_replace('Command/Generate.php', 'Profile/make', __FILE__);
      $makefiles = array_diff(scandir($makedir), array('..', '.'));

      foreach ($makefiles as $makefile) {
        $profile = str_replace('.make', '', $makefile);
        $options[$profile] = $profile;
      }

      $make = drush_choice($options, 'Choose a Drupal make profile');
    }
    else {
      $make = drush_get_option('profile');
    }

    $make = str_replace('Command/Generate.php', 'Profile/make/' . $make . '.make', __FILE__);
    drush_log(dt('Downloading Drupal, this will take a moment.'), 'warning');

    if ($exists) {
      drush_set_option('no-core', TRUE);
    }
    else {
      drush_set_option('prepare-install', TRUE);
    }

    drush_invoke('make', array($make, 'docroot'));
  }

}
