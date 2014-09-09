<?php

function _vera_audit_page_modules() {
  // Get a list of all modules currently listed in the .info file.
  $profile = drupal_get_profile();
  $info = file_get_contents(drupal_get_path('profile', $profile) . '/' . $profile . '.info');
  preg_match_all('/dependencies\[\] ?= ?(.*)/', $info, $modules);

  // Gather module metadata for display.
  $module_list = system_rebuild_module_data();

  $header = array(t('Module'), t('Status'));
  $rows = array();

  // Loop through each module in the .info file.
  foreach ($modules[1] as $module) {
    $audit_status = (module_exists($module)) ? 'exists' : 'new';

    $rows[] = array(
      $module_list[$module]->info['name'],
      '<div class="audit-status audit-status-' . $audit_status . '"></div>',
    );
  }

  // Loop through each currently enabled Drupal module.
  foreach (module_list() as $module) {
    if (in_array($module, $modules[1]) || $module_list[$module]->info['package'] == 'Core')
      continue;

    $rows[] = array(
      $module_list[$module]->info['name'],
      '<div class="audit-status audit-status-new"></div>',
    );
  }

  // Sort for display.
  asort($rows);

  // Theme the output in an audit table.
  drupal_add_css(drupal_get_path('module', 'vera') . '/audit/audit.css');
  return theme('table', array('header' => $header, 'rows' => $rows));
}
