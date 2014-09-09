<?php

/**
 * Check Drupal tests against PivotalTracker project.
 *
 * Test coverage is calculated against Stories which are categorized as Features in
 * Pivotal.  These are grouped by Epics for reporting coverage percentage and
 * missed stories.  Any stories tagged with "no test" will be ignored.
 * 
 * Tests are expected to be located within the Drupal installation profile under
 * the /tests subdirectory.  All .test files will be scanned for a test function
 * matching the story number from Pivotal.
 */

namespace Vera\Command;

use \Vera\Pivotal;

class Coverage extends \Vera\Command {

  public $epics;
  public $stories;
  public $tests;
  public $features;
  public $ignored;
  public $missed;

  public $coverage;

  function __construct() {
    $this->ignored = array();
    parent::__construct();
  }

  function fire() {

    // Get the Pivotal settings from Vera.
    $project = parent::getSetting('pivotal.project', 'PivotalTracker project');
    $api_key = parent::getSetting('pivotal.api', 'PivotalTracker API key');

    // Initiate API calls to the Pivotal project.
    $pivotal = new Pivotal($api_key, $project);
    $this->epics = $pivotal->epics();
    $this->stories = $pivotal->stories();

    $this->processPivotalData();
    $this->verifyCoverage();
    $this->report();

  }

  function processPivotalData() {

    // Loop through the epics.
    foreach ($this->epics as $epic) {
      $this->tests[$epic->id] = strtolower($epic->name);
    }

    // Loop through the stories.
    foreach ($this->stories as $story) {
      // Skip any stories which are not features.
      if ($story->story_type != 'feature')
        continue;

      foreach ($story->labels as $label) {
        if (in_array($label->name, $this->tests)) {
          $this->features[$label->name][$story->id] = $story->name;
        }

        // Ignore if the story is labeled as "no test"
        if ($label->name == 'no test')
          $this->ignored[] = $story->id;
      }
    }

  }

  /**
   * Examines test files to calculate coverage.
   *
   * Tests are expected to be written as:
   *   function test[STORY#]() {
   *
   * If a test is found, $this->features is marked TRUE.
   */
  function verifyCoverage() {
    if (empty($this->features))
      return;

    parent::bootstrap();
    $profile = DRUPAL_ROOT . '/profiles/' . drupal_get_profile() . '/tests/';
    $tests = array_diff(scandir($profile), array('..', '.'));
    
    foreach ($tests as $test) {
      // Regex search for test function names.
      $test = file_get_contents($profile . $test);
      preg_match_all('/function test(\d+)\(/', $test, $matches);
      
      foreach ($this->features as $epic => $stories) {
        foreach ($stories as $id => $story) {
          // If a test is found, mark the feature as TRUE.
          if (in_array($id, $matches[1]))
            $this->features[$epic][$id] = TRUE;
        }
      }
    }

    // Loop through the features and calculate coverage.
    $overall = 0;
    foreach ($this->features as $epic => $stories) {
      $coverage = 0;
      foreach ($stories as $id => $story) {
        // Disregard if the story has been marked as "no test."
        if (in_array($id, $this->ignored)) {
          unset($stories[$id]);
          continue;
        }

        // If the story is TRUE, it's got a matching test.
        if ($story === TRUE) {
          $coverage++;
        } else {
          $this->missed[$epic][$id] = $story;
        }
      }
      $this->coverage[$epic] = ($coverage == 0) ? 0 : number_format(($coverage / count($stories)) * 100, 0);
      $overall += $this->coverage[$epic];
    }
    $this->coverage['overall'] = number_format($overall / count($this->features), 2);
  }

  function report() {
    // Overall project coverage percentage.
    $rows[] = array(
      'name' => "\e[1mOVERALL TEST COVERAGE:\e[0m\r\n",
      'coverage' => $this->percent($this->coverage['overall'])
    );

    foreach ($this->features as $epic => $stories) {
      // Overall Epic coverage percentage.
      $rows[] = array(
        'name' => "\e[1m" . $epic . "\e[0m\r\n",
        'coverage' => $this->percent($this->coverage[$epic])
      );

      // Don't continue if there are no missed stories.
      if (!isset($this->missed[$epic]))
        continue;

      // Format and print each story which does not have a test.
      foreach ($this->missed[$epic] as $number => $story) {
        $story = explode("\n", wordwrap($story, 50));
        $story[0] = sprintf('%7s  %s', "\e[1m" . $number . ":\e[0m", $story[0]);
        foreach ($story as $number => $line) {
          $rows[] = array('name' => sprintf('%2s  %s', "", $line), 'coverage' => '');
        }
        $rows[] = array('name' => '', 'coverage' => '');
      }
    }

    // Print everything as a table.
    print PHP_EOL;
    drush_print_table($rows, FALSE, array('name' => 80));
    print PHP_EOL;
  }

  private function percent($number) {
    $format = ($number == 100) ? parent::$green : (($number >= 50) ? parent::$yellow : parent::$red);
    return sprintf($format, empty($text) ? $number . '%' : $text);
  }

}