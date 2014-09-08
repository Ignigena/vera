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

    if (file_exists('docroot'))
      return drush_set_error('VERA_GENERATE_EXISTS', 'A Drupal installation already exists.');

    $this->chooseInstallation();

    $profile = str_replace('Command/Generate.php', 'Profile/vera', __FILE__);
    $fs = new Filesystem();
    $fs->mirror($profile, 'docroot/sites/all/modules/custom');
  }

  function chooseInstallation() {
    if (!drush_get_option('profile')) {
      $makedir = str_replace('Command/Generate.php', 'Profile/make', __FILE__);
      $makefiles = array_diff(scandir($makedir), array('..', '.'));

      foreach ($makefiles as $makefile) {
        $profile = str_replace('.make', '', $makefile);
        $options[$profile] = $profile;
      }

      $make = drush_choice($options, 'Choose a Drupal make profile');
    } else {
      $make = drush_get_option('profile');
    }

    $make = str_replace('Command/Generate.php', 'Profile/make/' . $make . '.make', __FILE__);
    drush_log(dt('Downloading Drupal, this will take a moment.'), 'warning');
    drush_invoke('make', array($make, 'docroot'));
  }

}
