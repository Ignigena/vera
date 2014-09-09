<?php

namespace Vera\Profile\Deploy;

class Acquia extends \Vera\Profile\Deploy {

  public $docroot;

  function __construct($profile) {
    parent::__construct($profile);

    $this->docroot = drush_prompt(dt('Acquia docroot'));
    $this->acquiaRequireLine();
    $this->postDeployTests();
  }

  function acquiaRequireLine() {
    $settings = file_get_contents('docroot/sites/default/settings.php');
    $settings .= <<<ACQUIA

if (file_exists('/var/www/site-php')) {
  require '/var/www/site-php/$this->docroot/$this->docroot-settings.inc';
}

ACQUIA;

    file_put_contents('docroot/sites/default/settings.php', $settings);
    drush_log(dt('Added Acquia require line to settings.php.'), 'ok');
  }

  function postDeployTests() {
    $deployHook = file_get_contents('hooks/test/post-code-deploy/run-tests.sh');
    $deployHook = str_replace('{{TEST}}', $this->profile, $deployHook);
    file_put_contents('hooks/test/post-code-deploy/run-tests.sh', $deployHook);
  }

}
