<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\Unit\Fixtures;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Entity\DummyEntity;

class FileEntity extends DummyEntity implements \IteratorAggregate, FileInterface {
  public ?FileEntity $original;

  protected int $status = 0;

  public function getIterator(): \Traversable {
    return new \ArrayIterator([]);
  }

  public function getFilename() {}

  public function setFilename($filename) {}

  public function getFileUri() {}

  public function setFileUri($uri) {}

  public function createFileUrl($relative = TRUE) {}

  public function getMimeType() {}

  public function setMimeType($mime) {}

  public function getSize() {}

  public function setSize($size) {}

  public function isPermanent() {}

  public function isTemporary() {}

  public function setTemporary() {}

  public function getCreatedTime() {}

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {}

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {}

  public function hasField($field_name) {}

  public function getFieldDefinition($name) {}

  public function getFieldDefinitions() {}

  public function get($field_name) {}

  public function set($field_name, $value, $notify = TRUE) {}

  public function getFields($include_computed = TRUE) {}

  public function getTranslatableFields($include_computed = TRUE) {}

  public function onChange($field_name) {}

  public function validate() {}

  public function isValidationRequired() {}

  public function setValidationRequired($required) {}

  public function isLatestTranslationAffectedRevision() {}

  public function setRevisionTranslationAffected($affected) {}

  public function isRevisionTranslationAffected() {}

  public function isRevisionTranslationAffectedEnforced() {}

  public function setRevisionTranslationAffectedEnforced($enforced) {}

  public function isDefaultTranslationAffectedOnly() {}

  public function hasTranslationChanges() {}

  public function isDefaultTranslation() {}

  public function isNewTranslation() {}

  public function getTranslationLanguages($include_default = TRUE) {}

  public function getTranslation($langcode) {}

  public function getUntranslated() {}

  public function hasTranslation($langcode) {}

  public function addTranslation($langcode, array $values = []) {}

  public function removeTranslation($langcode) {}

  public function isTranslatable() {}

  public function isNewRevision() {}

  public function setNewRevision($value = TRUE) {}

  public function getRevisionId() {}

  public function getLoadedRevisionId() {}

  public function updateLoadedRevisionId() {}

  public function isDefaultRevision($new_value = NULL) {}

  public function wasDefaultRevision() {}

  public function isLatestRevision() {}

  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {}

  public function setSyncing($status) {}

  public function isSyncing() {}

  public function getChangedTime() {}

  public function setChangedTime($timestamp) {}

  public function getChangedTimeAcrossTranslations() {}

  public function getOwner() {}

  public function setOwner(UserInterface $account) {}

  public function getOwnerId() {}

  public function setOwnerId($uid) {}

  public function setPermanent(): void {
    $this->status = File::STATUS_PERMANENT;
  }

}
