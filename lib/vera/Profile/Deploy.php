<?php

namespace Vera\Profile;

use Symfony\Component\Filesystem\Filesystem;

class Deploy {

  function __construct() {
    $this->prepareSupportFiles();

    symlink('docroot', 'htdocs');
    drush_log(dt('Created a symlink from htdocs -> docroot.'), 'ok');
    
    file_put_contents('.gitignore', 'docroot/sites/all/modules/development');
    drush_log(dt('Ignore development modules from GIT.'), 'ok');
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