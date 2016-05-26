<?php

/**
 * @file
 * Contains \Drupal\acquia_purge\Plugin\Purge\TagsHeader\AcquiaPurgeTagsHeader.
 */

namespace Drupal\acquia_purge\Plugin\Purge\TagsHeader;

use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderBase;

/**
 * Exports te X-Acquia-Purge-Tags header.
 *
 * @PurgeTagsHeader(
 *   id = "acquiapurgetagsheader",
 *   header_name = "X-Acquia-Purge-Tags",
 * )
 */
class AcquiaPurgeTagsHeader extends TagsHeaderBase implements TagsHeaderInterface {}
