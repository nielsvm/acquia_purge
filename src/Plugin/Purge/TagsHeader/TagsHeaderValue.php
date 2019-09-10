<?php

namespace Drupal\acquia_purge\Plugin\Purge\TagsHeader;

/**
 * Provides simple value object for cache tag headers.
 */
class TagsHeaderValue {

  /**
   * String: separation character used.
   */
  const SEPARATOR = ' ';

  /**
   * @var string[]
   */
  protected $tags = [];

  /**
   * @var string[]
   */
  protected $tagsHashed = [];

  /**
   * Constructs a TagsHeaderValue object.
   *
   * @throws \LogicException
   *   Thrown when both tags arrays aren't of equal length.
   *
   * @param string[] $tags
   *   Non-associative array cache tags.
   * @param string[] $tags_hashed
   *   Non-associative array with hashed cache tags.
   */
  public function __construct($tags, $tags_hashed) {
    if (count($tags) !== count($tags_hashed)) {
      throw new \LogicException("TagsHeaderValue received unequal tag sets!");
    }
    $this->tags = $tags;
    $this->tagsHashed = $tags_hashed;
  }

  /**
   * Generate the header value for a cache tags header.
   *
   * @return string
   */
  public function __toString() {
    return implode(self::SEPARATOR, $this->tagsHashed);
  }

  /**
   * Get an associative array mapping keys.
   *
   * @return array
   */
  public function getTagsMap() {
    return array_combine($this->tags, $this->tagsHashed);
  }

}
