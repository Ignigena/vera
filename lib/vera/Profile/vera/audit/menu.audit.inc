<?php

function _vera_audit_page_menu() {
  $profile = drupal_get_profile();
  $menu = yaml_parse_file(drupal_get_path('profile', $profile) . '/config/menu_items.yml');

  drupal_add_css(drupal_get_path('module', 'vera') . '/audit/audit.css');
  $header = array(t('Title'), t('Path'), t('Menu'), t('Status'));
  $rows = array();

  foreach ($menu as $item) {
    $link_exists = db_select('menu_links', 'ml')->fields('ml')
      ->condition('ml.link_title', $item['link_title'])
      ->condition('ml.link_path', $item['link_path'])
      ->condition('ml.menu_name', $item['menu_name']);

    if (isset($item['weight']))
      $link_exists->condition('ml.weight', $item['weight']);

    $link_exists = $link_exists->execute()->fetchObject();
    $audit_status = (!empty($link_exists)) ? 'exists' : 'new';

    $rows[] = array(
      $item['link_title'],
      l($item['link_path'], $item['link_path']),
      (!empty($link_exists)) ? l($item['menu_name'], 'admin/structure/menu/item/' . $link_exists->mlid . '/edit') : $item['menu_name'],
      '<div class="audit-status audit-status-' . $audit_status . '"></div>',
    );
  }

  return theme('table', array('header' => $header, 'rows' => $rows));
}
