<?php

namespace Vera\Profile;

use Symfony\Component\Filesystem\Filesystem;

class Deploy {

  public $profile;

  function __construct($profile) {
    $this->prepareSupportFiles();
    $this->profile = $profile;
  }

  function prepareSupportFiles() {
    $deploy = strtolower(get_class($this));
    $deploy = array_pop(explode('\\', $deploy));
    $profile = str_replace('Deploy.php', 'deployment/' . $deploy, __FILE__);

    if (!file_exists($profile))
      return;

    $fs = new Filesystem();
    $fs->mirror($profile, '.');
    drush_log(dt('Copied deployment support files.'), 'ok');
  }

}