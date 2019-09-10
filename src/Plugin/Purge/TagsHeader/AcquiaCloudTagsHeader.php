<?php

namespace Drupal\acquia_purge\Plugin\Purge\TagsHeader;

use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderBase;
use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\acquia_purge\Plugin\Purge\TagsHeader\TagsHeaderValue;

/**
 * Exports the X-Acquia-Purge-Tags header.
 *
 * @PurgeTagsHeader(
 *   id = "acquiapurgecloudtagsheader",
 *   header_name = "X-Acquia-Purge-Tags",
 *   dependent_purger_plugins = {"acquia_purge"},
 * )
 */
class AcquiaCloudTagsHeader extends TagsHeaderBase implements TagsHeaderInterface {

  /**
   * {@inheritdoc}
   */
  public function getValue(array $tags) {
    return new TagsHeaderValue($tags, Hash::cacheTags($tags));
  }

}
