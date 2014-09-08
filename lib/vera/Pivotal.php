<?php

namespace Vera;

class Pivotal {

  public $c;
  public $project;

  function __construct($token, $project = NULL) {
    if (isset($project))
      $this->project = $project;
    
    $this->c = curl_init();
    curl_setopt($this->c, CURLOPT_HEADER, 0);
    curl_setopt($this->c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->c, CURLOPT_TIMEOUT, 10);
    curl_setopt($this->c, CURLOPT_HTTPHEADER, array('X-TrackerToken: ' . $token));
  }

  function get($endpoint) {
    curl_setopt($this->c, CURLOPT_URL, 'https://www.pivotaltracker.com/services/v5/' . $endpoint);
    return $this;
  }

  function execute() {
    return curl_exec($this->c);
  }

  function epics() {
    if (empty($this->project))
      throw new Exception('Project required.');

    return json_decode($this->get('projects/' . $this->project . '/epics')->execute());
  }

  function stories() {
    if (empty($this->project))
      throw new Exception('Project required.');

    return json_decode($this->get('projects/' . $this->project . '/stories?limit=500')->execute());
  }

  function __destruct() {
    curl_close($this->c);
  }

}
