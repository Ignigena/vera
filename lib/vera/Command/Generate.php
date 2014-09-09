<?php

/**
 * @todo: Docblock.
 */

namespace Vera\Command;

use Symfony\Component\Filesystem\Filesystem;

class Generate extends \Vera\Command {

  public $profile;
  public $profileMachine;
  public $profileClass;
  public $profilePath;
  public $make;

  function fire() {
    if (!drush_confirm(dt('Generate new site structure in the current working directory?')))
      return;

    if (file_exists('docroot') && !drush_confirm(dt('A Drupal installation already exists. Continue?')))
      return;

    $this->chooseInstallation(file_exists('docroot'));

    $profile = str_replace('Command/Generate.php', 'Profile/vera', __FILE__);
    $fs = new Filesystem();
    $fs->mirror($profile, 'docroot/sites/all/modules/custom/vera');
    drush_log(dt('Installed Vera helper module.'), 'ok');

    $this->chooseDeployment();

    symlink('docroot', 'htdocs');
    drush_log(dt('Created a symlink from htdocs -> docroot.'), 'ok');
    
    file_put_contents('.gitignore', 'docroot/sites/all/modules/development');
    drush_log(dt('Ignore development modules from GIT.'), 'ok');

    $this->generateProfile();
    $this->generateTests();
  }

  function chooseInstallation($exists) {
    $make = $this->promptDirectoryAsOptions('profile', 'Profile/make', 'Choose a Drupal make profile');
    $this->make = str_replace('Command/Generate.php', 'Profile/make/' . $make . '.make', __FILE__);

    if ($exists) {
      drush_set_option('no-core', TRUE);
    }
    else {
      drush_set_option('prepare-install', TRUE);
    }

    drush_invoke('make', array($this->make, 'docroot'));
  }

  function chooseDeployment() {
    $command = $this->promptDirectoryAsOptions('deploy', 'Profile/Deploy', 'Choose a deployment strategy');
    $command = '\Vera\Profile\Deploy\\' . ucfirst($command);

    if (class_exists($command))
      new $command();
  }

  function generateProfile() {
    $this->profile = parent::getSetting('name', 'Enter the name of the install profile to create');
    $this->profileMachine = strtolower(preg_replace('/[^a-zA-Z]+/', '', $this->profile));
    $this->profileClass = preg_replace('/[^a-zA-Z]+/', '', $this->profile);

    $this->profilePath = 'docroot/profiles/' . $this->profileMachine;
    mkdir($this->profilePath);
    mkdir($this->profilePath . '/config');
    symlink($this->profilePath, 'profile');

    $info = <<<INFO
name = $this->profile
description = Install profile for $this->profile.
version = 1.0
core = 7.x

dependencies[] = vera


INFO;

    $makeFile = file_get_contents($this->make);
    preg_match_all('/projects\[(.*)\]\[version\]/', $makeFile, $modules);
    asort($modules[1]);
    foreach ($modules[1] as $module) {
      if ($module == 'drupal')
        continue;
      $info .= 'dependencies[] = ' . $module . PHP_EOL;
    }

    $info .= PHP_EOL . 'files[] = tests/emergo.test' . PHP_EOL;
    file_put_contents($this->profilePath . '/' . $this->profileMachine . '.info', $info);

    $install = <<<INSTALL
<?php
/**
 * @file
 * Install, update and uninstall functions for the $this->profile installation profile.
 */
module_load_include('inc', 'vera', 'vera.profile');

/**
 * Implements hook_install().
 */
function {$this->profileMachine}_install() {
  \$profile = new {$this->profileClass}Profile();
  \$profile->install();
}

/**
 * Implements $this->profile install profile.
 * Extends Vera helper install profile.
 */
class {$this->profileClass}Profile extends VeraProfile {

  function __construct() {
    parent::__construct();
  }

  function install() {
    parent::install();

    // Implement custom installation steps here.
  }

}
INSTALL;
    file_put_contents($this->profilePath . '/' . $this->profileMachine . '.install', $install);

    $profile = <<<PROFILE
<?php
/**
 * @file
 * Enables modules and site configuration for $this->profile site installation.
 */

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function {$this->profileMachine}_form_install_configure_form_alter(&\$form, \$form_state) {
  \$form['site_information']['site_name']['#default_value'] = '{$this->profile}';
}

PROFILE;
    file_put_contents($this->profilePath . '/' . $this->profileMachine . '.profile', $profile);

    drush_log(dt('Created base install profile at ' . $this->profilePath . '.'), 'ok');
  }

  function generateTests() {
    mkdir($this->profilePath . '/tests');

    $test = <<<TEST
<?php

require_once DRUPAL_ROOT . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

class {$this->profileClass}WebTestCase extends VeraWebTestCase {
  protected \$profile = '$this->profileMachine';
}

class {$this->profileClass}DistributionTestCase extends {$this->profileClass}WebTestCase {

  public static function getInfo() {
    return parent::veraTest('Distribution', 'Test distribution setup and configuration.');
  }

  /**
   *
   * Each test is written as:
   *
   *   public function testFoo() {
   *     \$this->drupalGet('bar');
   *     \$this->assertSomething();
   *   }
   *
   * Whenever possible, use the PivotalTracker story number in the function name.
   * This will ensure that test coverage calculations are accurate.
   *
   */

}
TEST;
    file_put_contents($this->profilePath . '/tests/' . $this->profileMachine . '.test', $test);
    drush_log(dt('Added basic testing framework to install profile.'), 'ok');
  }

}
