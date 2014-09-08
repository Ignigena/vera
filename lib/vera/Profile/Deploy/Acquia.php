<?php

namespace Vera\Profile\Deploy;

class Acquia extends \Vera\Profile\Deploy {

  public $docroot;

  function __construct() {
    parent::__construct();

    $this->docroot = drush_prompt(dt('Acquia docroot'));
    $this->acquiaRequireLine();
  }

  function acquiaRequireLine() {
    $settings = file_get_contents('docroot/sites/default/settings.php');
    $settings .= <<<ACQUIA

if (file_exists('/var/www/site-php')) {
  require '/var/www/site-php/$this->docroot/$this->docroot-settings.inc';
}

ACQUIA;

    file_put_contents('docroot/sites/default/settings.php', $settings);
  }

}
