<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\Concerns;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

trait UploadsFiles {

  abstract protected function getFileStorage(): EntityStorageInterface;

  protected function saveFileField(FormStateInterface $form_state, string $fieldName): ?File {
    $fileData = $form_state->getValue($fieldName);

    $oldFile = $this->getOldFile($form_state, $fieldName);

    if (!$fileData) {
      if ($oldFile) {
        $this->getFileStorage()->delete([$oldFile]);
      }

      return NULL;
    }

    $newFile = File::load($fileData[0]);
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

  protected function getNewFileUriFromRename(FileInterface $file, string $newFilenameWithoutExtension): string {
    $oldFilename = $file->getFilename();
    $oldParts = explode('.', $oldFilename);
    $newFilename = basename($newFilenameWithoutExtension) . '.' . end($oldParts);

    return str_replace($oldFilename, $newFilename, $file->getFileUri());
  }

  protected function getOldFile(FormStateInterface $form_state, string $fieldName): ?File {
    $oldFileId = reset($form_state->getCompleteForm()[$fieldName]['#default_value']);
    if (!$oldFileId) {
      return NULL;
    }

    return $this->getFileStorage()->load($oldFileId);
  }

}
