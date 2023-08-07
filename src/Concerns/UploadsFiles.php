<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\Concerns;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;

trait UploadsFiles {

  abstract protected function getFileStorage(): EntityStorageInterface;

  protected function saveFileField(?int $oldFileId, ?int $newFileId): ?FileInterface {
    $oldFile = $this->loadFileFromId($oldFileId);
    $newFile = $this->loadFileFromId($newFileId);
    $oldFileExists = $oldFile instanceof FileInterface;
    $newFileExists = $newFile instanceof FileInterface;

    // If there is no new file, delete the old one if it exists and return.
    if (!($newFileExists)) {
      if ($oldFileExists) {
        $this->getFileStorage()->delete([$oldFile]);
      }

      return NULL;
    }

    $fileIsReplaced = $oldFileExists && $newFile->uuid() !== $oldFile->uuid();
    if ($fileIsReplaced) {
      $this->getFileStorage()->delete([$oldFile]);
    }

    // Make new file permament.
    $newFile->setPermanent();
    $newFile->save();

    return $newFile;
  }

  protected function getFileIdFromUuid(?string $uuid): ?int {
    if (!$uuid) {
      return NULL;
    }

    $foundFiles = $this->getFileStorage()->loadByProperties(['uuid' => $uuid]);
    if ($newFile = reset($foundFiles)) {
      return (int) $newFile->id();
    }

    return NULL;
  }

  protected function updateModuleConfig(Config $moduleConfig, ?FileInterface $newFile): void {
    if ($newFile) {
      $moduleConfig->set('uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('uuid', NULL);
    }
    $moduleConfig->save();
  }

  protected function updateThemeConfig(Config $themeConfig, ?FileInterface $newFile, string $fieldName): void {
    if ($newFile) {
      $themeConfig->set("{$fieldName}.use_default", FALSE);
      $themeConfig->set("{$fieldName}.path", $newFile->getFileUri());
    }
    else {
      $themeConfig->set("{$fieldName}.use_default", TRUE);
      $themeConfig->set("{$fieldName}.path", '');
    }
    $themeConfig->save();
  }

  protected function loadFileFromId(?int $fileId): ?FileInterface {
    if (!$fileId) {
      return NULL;
    }

    return $this->getFileStorage()->load($fileId);
  }

  /**
   * Please note that this method does not support multi-value fields.
   */
  protected function getOldFileId(FormStateInterface $form_state, string $fieldName): ?int {
    $fieldValue = $form_state->getCompleteForm()[$fieldName]['#default_value'];
    $firstFieldValue = reset($fieldValue);

    return ((int) $firstFieldValue) ?: NULL;
  }

  /**
   * Please note that this method does not support multi-value fields.
   */
  protected function getNewFileId(FormStateInterface $form_state, string $fieldName): ?int {
    $fieldValue = $form_state->getValue($fieldName);
    $firstFieldValue = reset($fieldValue);

    return ((int) $firstFieldValue) ?: NULL;
  }

}
