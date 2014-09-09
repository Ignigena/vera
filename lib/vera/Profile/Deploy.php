<?php

namespace Vera\Profile;

use Symfony\Component\Filesystem\Filesystem;

class Deploy {

  function __construct() {
    $this->prepareSupportFiles();
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