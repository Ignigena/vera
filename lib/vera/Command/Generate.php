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

    symlink('docroot', 'htdocs');
    drush_log(dt('Created a symlink from htdocs -> docroot.'), 'ok');
    
    file_put_contents('.gitignore', 'docroot/sites/all/modules/development');
    drush_log(dt('Ignoring development modules from GIT.'), 'ok');

    $this->generateProfile();
    $this->generateTests();
    $this->chooseDeployment();

    $setup = <<<SETUP
#!/bin/sh

# Add a pre-commit hook to compile SASS using Compass.
curl -silent https://gist.githubusercontent.com/gheydon/6171813/raw/pre-commit > .git/hooks/pre-commit
chmod 755 .git/hooks/pre-commit

# Install all Composer dependencies.
composer install -d ./docroot

# Create the database table if it doesn't already exist.
RESULT=`mysql -u root --skip-column-names -e "SHOW DATABASES LIKE '$this->profileMachine'"`
if [ "\$RESULT" != "$this->profileMachine" ]; then
  mysql -u root -e "CREATE DATABASE $this->profileMachine"
fi

# Install the Drupal site profile.
cd docroot && drush site-install $this->profileMachine --db-url=mysql://root@localhost/$this->profileMachine --site-name="$this->profile" --account-name=support -y
SETUP;
    file_put_contents('setup.sh', $setup);
    chmod('setup.sh', 0755);
    drush_log(dt('Created setup script.'), 'ok');

    $htaccess = file_get_contents('docroot/.htaccess');
    $htaccess = str_replace('# RewriteBase /' . PHP_EOL, 'RewriteBase /' . PHP_EOL, $htaccess);
    file_put_contents('docroot/.htaccess', $htaccess);
    drush_log(dt('Modified .htaccess for clean URL functionality.'), 'ok');

    $this->createReadMe();
    drush_log(dt('Running setup script, this may take a moment.'), 'warning');
    system('./setup.sh');
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
      new $command($this->profileClass);
  }

  function generateProfile() {
    $composer = <<<COMPOSER
{
  "require": {
    "symfony/yaml": "2.5.*"
  }
}
COMPOSER;
    file_put_contents('docroot/composer.json', $composer);

    $this->profile = parent::getSetting('name', 'Enter the name of the Drupal site to create');
    $this->profileMachine = strtolower(preg_replace('/[^a-zA-Z]+/', '', $this->profile));
    parent::saveSetting('profile', $this->profileMachine);
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
    $info .= $this->parseMakeModules($this->make);
    $info .= $this->parseMakeModules(str_replace('Command/Generate.php', 'Profile/make/common.make.inc', __FILE__));
    $info .= PHP_EOL . 'files[] = tests/' . $this->profileMachine . '.test' . PHP_EOL;
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
    \$this->profile = drupal_get_path('profile', '{$this->profileMachine}');
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

  function createReadMe() {
    $readme = <<<README
# $this->profile

A Drupal website created with Vera.

### Getting Started ###

#### Technical Requirements ####

* PHP 5.4+
* Composer
* Vera

#### Installation and Setup ####

* Clone the codebase repo locally to `~/sites/{$this->profileMachine}`.
* Run `./setup.sh` to configure GIT hook and install Composer dependencies.
* Credentials are supplied once the setup script has finished.

To rebuild installation from scratch during development run `vera nuke`

### Contribution guidelines ###

* Each epic has a corresponding test group defined in
`profiles/{$this->profileMachine}/tests`.
* Each story requires a corresponding passing test in the group for that epic.
* Test coverage can be verified against Pivotal by running `vera coverage`.
* Prior to code freeze, all core features must be implemented within
installation profile. A complete environment must be created during the
installation process with no database cloning required.
* Post code freeze, all structural changes to existing features will require a
corresponding update hook. Update hooks should be placed either in the install
profile (for generic/platform features or to enable modules) or within an
already-exisitng custom module relating to the feature (to update
module-specific features orcapabilities.)
README;
    file_put_contents('README.md', $readme);
    drush_log(dt('Added README file to project.'), 'ok');
  }

  private function parseMakeModules($path) {
    $makeFile = file_get_contents($path);
    preg_match_all('/projects\[(.*)\]\[version\]/', $makeFile, $modules);
    preg_match_all('/projects\[(.*)\]\[exclude\]/', $makeFile, $modules_exclude);
    asort($modules[1]);

    $results = '';

    foreach ($modules[1] as $module) {
      if ($module == 'drupal' || (isset($modules_exclude[1]) && in_array($module, $modules_exclude[1])))
        continue;
      $results .= 'dependencies[] = ' . $module . PHP_EOL;
    }

    return $results;
  }

}
