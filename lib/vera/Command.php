<?php

namespace Vera;

class Command {

  public $settingsFile;
  
  public static $red = "\033[31;40m\033[1m%s\033[0m";
  public static $yellow = "\033[1;33;40m\033[1m%s\033[0m";
  public static $green = "\033[1;32;40m\033[1m%s\033[0m";

  function __construct() {
    $this->settingsFile = getcwd() . '/vera.json';
    $this->fire();
  }

  public function getSetting($key, $prompt) {

    // Ensure Vera settings file exists or create one if it doesn't.
    if (!file_exists($this->settingsFile)) {
      $this->writeSettings(array());
    }

    // Read the Vera settings file.
    $settings = file_get_contents($this->settingsFile, 'r');
    $settings = json_decode($settings);

    // If the key exists, no further action required.
    if ($settings->$key)
      return $settings->$key;

    // If the key doesn't exist, prompt for user input.
    $settings->$key = drush_prompt($prompt);
    $this->writeSettings($settings);

    // Return the requested key.
    return $settings->$key;

  }

  private function writeSettings($settings) {
    $fp = fopen($this->settingsFile, 'w');
    fwrite($fp, json_encode($settings));
    fclose($fp);
  }

  public function bootstrap() {
    drush_bootstrap_max();
  }

  public function fire() {

    // This function intentionally left blank.
    // Vera commands will perform all main functions here.

  }

}
