<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\Concerns;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileStorage;

trait UploadsFiles {

  abstract protected function getFileStorage(): FileStorage;

  protected function saveFileField(?int $oldFileId, ?int $newFileId): ?File {
    $oldFile = $this->loadFileFromId($oldFileId);

    // If there is no new file, delete the old one.
    if (!$newFileId) {
      if ($oldFile) {
        $this->getFileStorage()->delete([$oldFile]);
      }

      return NULL;
    }

    $newFile = $this->loadFileFromId($newFileId);
    if (!($newFile instanceof FileInterface)) {
      return NULL;
    }

    $newFile->setPermanent();
    $newFile->save();

    // File has been changed, delete the old one.
    if ($oldFile && $newFile->uuid() !== $oldFile->uuid()) {
      $this->getFileStorage()->delete([$oldFile]);
    }

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

  protected function getNewFileId(FormStateInterface $form_state, string $fieldName): ?int {
    $fieldValue = reset($form_state->getValue($fieldName));

    return ((int) $fieldValue) ?: NULL;
  }

  protected function getOldFileId(FormStateInterface $form_state, string $fieldName): ?int {
    $fieldValue = reset($form_state->getCompleteForm()[$fieldName]['#default_value']);

    return ((int) $fieldValue) ?: NULL;
  }

}
