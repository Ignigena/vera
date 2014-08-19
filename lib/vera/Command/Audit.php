<?php

namespace Vera\Command;

class Audit extends \Vera\Command {

  function fire() {

    if (!drush_get_option('check')) {
      $audit_dir = str_replace('Command/Audit.php', 'Audit', __FILE__);
      $scripts = array_diff(scandir($audit_dir), array('..', '.'));

      foreach ($scripts as $script) {
        $class = str_replace('.php', '', $script);
        $options[$class] = $class;
      }

      $audit = drush_choice($options, 'Run audit');
    } else {
      $audit = drush_get_option('check');
    }

    $command = '\Vera\Audit\\' . ucfirst($audit);

    if (class_exists($command))
      new $command();

  }

}