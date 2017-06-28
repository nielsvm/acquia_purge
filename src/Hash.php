<?php

namespace Drupal\acquia_purge;

/**
 * Helper class that centralizes string hashing for security and maintenance.
 */
class Hash {

  /**
   * Hardcoded cache tags.
   *
   * This array contains the most common cache tags that core generates and each
   * array key represents an ID used instead of a longer hashed version. This
   * saves 2 to 3 characters per tag, which is a significant help in reducing
   * HTTP header values. Don't change tags or keys, only append new ones!
   *
   * @var string
   */
  private static $cacheTagHardcodes = [
    'a'  => 'entity_field_info',
    'b'  => 'entity_types',
    'c'  => '4xx-response',
    'd'  => 'entity_bundles',
    'e'  => 'local_task',
    'f'  => 'contextual_links_plugins',
    'g'  => 'link_relation_type',
    'h'  => 'local_action',
    'i'  => 'element_info_build',
    'j'  => 'theme_registry',
    'k'  => 'routes',
    'l'  => 'route_match',
    'm'  => 'http_response',
    'n'  => 'config:system.menu.admin',
    'o'  => 'config:system.menu.account',
    'p'  => 'config:system.menu.main',
    'q'  => 'config:system.menu.tools',
    'r'  => 'breakpoints',
    's'  => 'config:system.menu.footer',
    't'  => 'file_list',
    'u'  => 'comment_list',
    'v'  => 'node_list',
    'w'  => 'config:entity_view_mode_list',
    'x'  => 'rendered',
    'y'  => 'config:action_list',
    'z'  => 'config:user_role_list',
    '0'  => 'config:contact_form_list',
    '1'  => 'user_list',
    '2'  => 'config:user.role.authenticated',
    '3'  => 'config:user.role.anonymous',
    '4'  => 'config:filter_format_list',
    '5'  => 'config:shortcut_set_list',
    '6'  => 'config:menu_list',
    '7'  => 'config:editor_list',
    '8'  => 'config:entity_form_display_list',
    '9'  => 'config:entity_form_mode_list',
    '10' => 'config:entity_view_display_list',
    '11' => 'config:contact.form.feedback',
    '12' => 'config:image_style_list',
    '13' => 'config:node.settings',
    '14' => 'config:node_type_list',
    '15' => 'config:core.menu.static_menu_link_overrides',
    '16' => 'config:tour_list',
    '17' => 'views_data',
    '18' => 'user_view',
    '19' => 'block_content_view',
    '20' => 'comment_view',
    '21' => 'taxonomy_term_view',
    '22' => 'node_view',
    '23' => 'contact_message_view',
    '24' => 'config:block_content_type_list',
    '25' => 'config:block_list',
    '26' => 'config:comment_type_list',
    '27' => 'config:view_list',
    '28' => 'config:rdf_mapping_list',
    '29' => 'config:search_page_list',
    '30' => 'config:shortcut.set.default',
    '31' => 'config:taxonomy_vocabulary_list',
    '32' => 'config:views.view.who_s_online',
    '33' => 'config:views.view.archive',
    '34' => 'config:views.view.block_content',
    '35' => 'config:views.view.comments_recent',
    '36' => 'config:views.view.who_s_new',
    '37' => 'config:views.view.user_admin_people',
    '38' => 'config:views.view.taxonomy_term',
    '39' => 'config:views.view.glossary',
    '40' => 'config:views.view.frontpage',
    '41' => 'config:views.view.files',
    '42' => 'config:views.view.content_recent',
    '43' => 'config:views.view.content',
    '44' => 'config:core.extension',
    '45' => 'user:1'
  ];

  /**
   * Create unique hashes/IDs for a list of cache tag strings.
   *
   * @param string[] $tags
   *   Non-associative array cache tags.
   *
   * @return string[]
   *   Non-associative array with hashed copies of the given cache tags.
   */
  static public function cacheTags(array $tags) {
    $hashes = [];
    foreach ($tags as $tag) {
      if (($id = array_search($tag, SELF::$cacheTagHardcodes)) !== FALSE) {
        $hashes[] = $id;
      }
      else {
        $hashes[] = substr(md5($tag), 0, 4);
      }
    }
    return $hashes;
  }

  /**
   * Create a unique hash that identifies this site.
   *
   * @param string $site_name
   *   The identifier of the site on Acquia Cloud.
   * @param string $site_path
   *   The path of the site, e.g. 'site/default' or 'site/database_a'.
   *
   * @return string
   *   Cryptographic hash with a length of 8.
   */
  static public function siteIdentifier($site_name, $site_path) {
    return substr(md5($site_name . $site_path), 0, 8);
  }

}
