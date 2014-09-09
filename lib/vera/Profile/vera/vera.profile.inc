<?php

require_once DRUPAL_ROOT . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

class VeraProfile {
  public $theme;
  public $text_formats;
  public $blocks;
  public $content_types;
  public $menus;
  public $menu_items;
  public $profile;

  function __construct() {
    $this->theme = array(
      'default' => 'bartik',
      'admin' => 'seven',
    );

    $this->profile = drupal_get_path('module', 'vera');

    $this->blocks = array();
    $this->parseBlockManifest();

    $this->menus = array();
    $this->menu_items = array();
    $this->parseManifest('menu_items', $this->menu_items);

    $this->text_formats = array();
    $this->parseManifest('formats', $this->text_formats);

  }

  public function install() {
    $this->createTextFormats();
    $this->createBlocks();
    $this->createUsersAndRoles();
    $this->createContentTypes();
    $this->createFields();
    $this->setupTheme();
  }

  public function createTextFormats() {
    foreach ($this->text_formats as $format) {
      filter_format_save((object)$format);
    }
  }

  public function parseManifest($manifest, &$merge, $t = array()) {
    if (empty($merge) || !is_array($merge))
      $merge = array();

    // If no manifest exists for the install profile, stop here.
    if (!file_exists($this->profile . '/config/' . $manifest . '.yml')) {
      $manifest = array();
    }
    // Otherwise, parse the manifest file into an array.
    else {
      $manifest = Yaml::parse(file_get_contents($this->profile . '/config/' . $manifest . '.yml'));
      if (!empty($t)) {
        foreach ($manifest as $row) {
          foreach ($t as $field) {
            if (isset($row[$field]))
              $row[$field] = st($row[$field]);
          }
        }
      }
    }

    // Merge the contents of the manifest into the array.
    $merge = array_merge($merge, $manifest);
  }

  public function parseBlockManifest($manifest = 'blocks') {
    $new_blocks = Yaml::parse(file_get_contents($this->profile . '/config/' . $manifest . '.yml'));
    $this->blocks = array_merge($this->blocks, $new_blocks);
  }

  public function createBlocks() {
    $query = db_insert('block')->fields(array('module', 'delta', 'theme', 'status', 'weight', 'region', 'pages', 'cache'));
    foreach ($this->blocks as $block) {
      $query->values($block);
    }
    $query->execute();
  }

  public function createContentTypes() {

    // If no content types are defined, stop now.
    if (!is_array($this->content_types) || empty($this->content_types))
      return;

    // Create all defined content types.
    foreach ($this->content_types as $type) {
      $type = node_type_set_defaults($type);
      node_type_save($type);
      if (!isset($type->body) || $type->body != FALSE) {
        node_add_body_field($type);
      }
    }

  }

  public function createUsersAndRoles() {
    // Enable default permissions for system roles.
    user_role_grant_permissions(DRUPAL_ANONYMOUS_RID, array('access content', 'use text format filtered_html'));
    user_role_grant_permissions(DRUPAL_AUTHENTICATED_RID, array('access content', 'use text format filtered_html'));

    // Create a default role for site administrators, with all available permissions assigned.
    $admin_role = new stdClass();
    $admin_role->name = 'administrator';
    $admin_role->weight = 2;
    user_role_save($admin_role);
    user_role_grant_permissions($admin_role->rid, array_keys(module_invoke_all('permission')));
    // Set this as the administrator role.
    variable_set('user_admin_role', $admin_role->rid);

    // Assign user 1 the "administrator" role.
    db_insert('users_roles')
      ->fields(array('uid' => 1, 'rid' => $admin_role->rid))
      ->execute();

    // Enable user picture support and set the default to a square thumbnail option.
    variable_set('user_pictures', '1');
    variable_set('user_picture_dimensions', '1024x1024');
    variable_set('user_picture_file_size', '800');
    variable_set('user_picture_style', 'thumbnail');

    // Only allow administrators to register accounts.
    variable_set('user_register', USER_REGISTER_ADMINISTRATORS_ONLY);
  }

  public function setupTheme() {
    // Enable all themes.
    db_update('system')
      ->fields(array('status' => 1))
      ->condition('type', 'theme')
      ->condition('name', $this->theme, 'IN')
      ->execute();

    // Enable the default theme.
    variable_set('theme_default', $this->theme['default']);

    // Enable the admin theme.
    variable_set('admin_theme', $this->theme['admin']);
    variable_set('node_admin_theme', '1');
  }

  public function setupMenus() {
    // Create any additional menus.
    foreach ($this->menus as $menu) {
      $menu['link'] = st($menu['title']);
      menu_save($menu);
    }
    // Create the main menu links.
    $this->buildMenu($this->menu_items);
    menu_rebuild();
  }

  public function buildMenu($menu_items, $parent = NULL) {
    foreach ($menu_items as $menu_item) {
      if ($parent) {
        $menu_item['plid'] = $parent['mlid'];
        $menu_item['menu_name'] = $parent['menu_name'];
      }
      $menu_item['link_title'] = st($menu_item['link_title']);

      // If there are children defined, save them for later.
      if (isset($menu_item['children'])) {
        $menu_item_children = $menu_item['children'];
        unset($menu_item['children']);
      }

      menu_link_save($menu_item);

      // Create each child with the correct parent ID.
      if (isset($menu_item_children)) {
        $this->buildMenu($menu_item_children, $menu_item);
        unset($menu_item_children);
      }
    }
  }
  public function createFields() {
    $this->parseManifest('fields', $fields);
    foreach ($fields as $field) {
      field_create_field($field);
    }

    $this->parseManifest('field_instances', $field_instances);
    foreach ($field_instances as $instance) {
      field_create_instance($instance);
    }
  }

  /**
   * Implements checkDependencies().
   * Graceful error handling for install steps requiring contrib modules.
   */
  private function checkDependencies($modules = array()) {
    foreach ($modules as $module) {
      if (!module_exists($module)) {
        drupal_set_message('The ' . $module . ' module is not installed and this step has been skipped.', 'error');
        return FALSE;
      }
    }
    return TRUE;
  }

  public function customBreadcrumbs() {
    if (!$this->checkDependencies(array('custom_breadcrumbs')))
      return;

    $types = array(
      'node' => 'custom_breadcrumb',
      'path' => 'custom_breadcrumbs_paths',
      'term' => 'custom_breadcrumbs_taxonomy_term',
      'vocabulary' => 'custom_breadcrumbs_taxonomy_vocabulary',
    );

    $this->parseManifest('breadcrumbs', $breadcrumbs);

    foreach ($breadcrumbs as $breadcrumb) {
      // Provide default values for convenience.
      $breadcrumb += array(
        'type' => 'node',
        'visibility_php' => '',
      );

      // Lookup the vocabulary ID by machine name.
      if (!empty($breadcrumb['vocabulary'])) {
        $vocab = taxonomy_vocabulary_machine_name_load($breadcrumb['vocabulary']);
        $breadcrumb['vid'] = $vocab->vid;
        unset($breadcrumb['vocabulary']);
      }

      // The 'type' determines which table to insert into.
      $type = $types[$breadcrumb['type']];
      unset($breadcrumb['type']);

      db_insert($type)
        ->fields($breadcrumb)
        ->execute();
    }
  }
}