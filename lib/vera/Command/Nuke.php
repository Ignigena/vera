<?php

namespace Vera\Command;

class Nuke extends \Vera\Command {

  function fire() {

    print <<<END

     _.-^^---....,,--
 _--                  --_
<                        >)
|                         |
 \._                   _./
    '''--. . , ; .--'''
          | |   |
       .-=||  | |=-.
        -=#$%&%$#=-
          | ;  :|
 _____.,-#%&$@%#&#~,._____

END;

    // Get the current install profile and site name.
    $profile = parent::getSetting('profile', 'Drupal profile');
    $sitename = parent::getSetting('name', 'Site name');

    // Wipe the database and reinstall the site.
    drush_set_option('site-name', $sitename);
    drush_set_option('account-name', 'support');
    drush_invoke('site-install', array($profile));

    // Drupal  must be bootstrapped before we continue.
    parent::bootstrap();

    // Reset the admin password to a highly secure password.
    drush_set_option('password', 'admin');
    drush_invoke('upwd', array('support'));

  }

}