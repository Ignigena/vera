<?php

namespace Vera\Command;

class Audit extends \Vera\Command {

  function fire() {

    $audit = $this->promptDirectoryAsOptions('check', 'Audit', 'Run audit');
    $command = '\Vera\Audit\\' . ucfirst($audit);

    if (class_exists($command))
      new $command();

  }

}