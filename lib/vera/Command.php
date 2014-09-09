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

    // Read the Vera settings file.
    $settings = $this->read();

    // If the key exists, no further action required.
    if ($settings->$key)
      return $settings->$key;

    // If the key doesn't exist, prompt for user input.
    $settings->$key = drush_prompt($prompt);
    $this->writeSettings($settings);

    // Return the requested key.
    return $settings->$key;

  }

  public function saveSetting($key, $value) {

    // Read the Vera settings file.
    $settings = $this->read();

    // Save the setting value.
    $settings->$key = $value;
    $this->writeSettings($settings);

    return TRUE;

  }

  private function read() {

    // Ensure Vera settings file exists or create one if it doesn't.
    if (!file_exists($this->settingsFile)) {
      $this->writeSettings(array());
    }

    // Read the Vera settings file.
    $settings = file_get_contents($this->settingsFile, 'r');

    // Return the encoded settings as an object.
    return (object)json_decode($settings);

  }

  private function writeSettings($settings) {
    $fp = fopen($this->settingsFile, 'w');
    fwrite($fp, json_encode($settings, JSON_PRETTY_PRINT));
    fclose($fp);
  }

  public function bootstrap() {
    drush_bootstrap_max();
  }

  public function fire() {

    // This function intentionally left blank.
    // Vera commands will perform all main functions here.

  }

  /**
   * Utility function to convert a directory of files into prompt options.
   *
   * @param string $drush_option
   *   The Drush option key to check before displaying the prompt.
   * @param string $dir
   *   The directory containing the files to be used as options.
   * @param string $question
   *   The question text used in the prompt.
   */
  public function promptDirectoryAsOptions($drush_option, $dir, $question) {

    // Check for the existence of the Drush option.
    if (drush_get_option($drush_option))
      return drush_get_option($drush_option);

    // Get the contents of the specified directory.
    $directory = str_replace('Command.php', $dir, __FILE__);
    $files = array_diff(scandir($directory), array('..', '.'));

    // Loop through each file and convert it to an option.
    foreach ($files as $file) {
      // Skip over any common files which aren't standalone.
      if (strpos($file, 'common') === 0)
        continue;
      $file = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file);
      $options[$file] = ucfirst($file);
    }

    // Return all available options as a Drush prompt.
    return drush_choice($options, $question . ':');

  }

}
