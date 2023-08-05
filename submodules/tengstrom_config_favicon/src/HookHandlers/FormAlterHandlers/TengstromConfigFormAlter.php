<?php

declare(strict_types=1);

namespace Drupal\tengstrom_config_favicon\HookHandlers\FormAlterHandlers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\tengstrom_configuration\Concerns\UploadsFiles;
use Drupal\tengstrom_configuration\Factories\ImageElementFactory;
use Drupal\tengstrom_configuration\ValueObjects\ImageElementOptions;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Ordermind\DrupalTengstromShared\HookHandlers\FormAlterHandlerInterface;

class TengstromConfigFormAlter implements FormAlterHandlerInterface {
  use UploadsFiles;
  use DependencySerializationTrait;

  protected ConfigFactoryInterface $configFactory;
  protected ImageElementFactory $elementFactory;
  protected EntityStorageInterface $fileStorage;
  protected FileRepositoryInterface $fileRepository;
  protected ThemeHandlerInterface $themeHandler;
  protected TranslationInterface $translator;
  protected UploadDimensions $uploadDimensions;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    ImageElementFactory $elementFactory,
    EntityTypeManagerInterface $entityTypeManager,
    FileRepositoryInterface $fileRepository,
    ThemeHandlerInterface $themeHandler,
    TranslationInterface $translator,
    array $tengstromConfiguration
  ) {
    if (empty($tengstromConfiguration['upload_dimensions']['favicon'])) {
      throw new \RuntimeException(
        'There must be a favicon upload dimensions entry in the tengstrom config parameter (likely defined in sites/default/services.yml)'
      );
    }

    $this->configFactory = $configFactory;
    $this->elementFactory = $elementFactory;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->fileRepository = $fileRepository;
    $this->themeHandler = $themeHandler;
    $this->translator = $translator;
    $this->uploadDimensions = UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['favicon']);
  }

  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    $config = $this->configFactory->get('tengstrom_config_favicon.settings');

    $options = new ImageElementOptions(
      label: $this->translator->translate('Favicon'),
      previewImageStyle: 'config_thumbnail',
      fileId: $this->getFileIdFromUuid($config->get('uuid')) ?? NULL,
      optimalDimensions: $this->uploadDimensions,
      maxSize: '2 KB'
    );

    $form['favicon'] = $this->elementFactory->create($options);
    $form['#submit'][] = [$this, 'submit'];
  }

  public function submit(array &$form, FormStateInterface $form_state): void {
    $moduleConfig = $this->configFactory->getEditable('tengstrom_config_favicon.settings');
    $oldFile = $this->getOldFile($form_state, 'favicon');
    $newFile = $this->saveFileField($form_state, 'favicon');
    if ($newFile) {
      $this->renameNewFile($oldFile, $newFile);
      $moduleConfig->set('uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('uuid', NULL);
    }
    $moduleConfig->save();

    $themeConfig = $this->configFactory->getEditable($this->themeHandler->getDefault() . '.settings');
    if ($newFile) {
      $themeConfig->set('favicon.use_default', FALSE);
      $themeConfig->set('favicon.path', $newFile->getFileUri());
    }
    else {
      $themeConfig->set('favicon.use_default', TRUE);
      $themeConfig->set('favicon.path', '');
    }
    $themeConfig->save();
  }

  /**
   * {@inheritDoc}
   */
  protected function getFileStorage(): EntityStorageInterface {
    return $this->fileStorage;

  }

  /**
   * This method is meant to ensure that the file is always called "favicon"
   * in the file system.
   */
  protected function renameNewFile(?FileInterface $oldFile, FileInterface $newFile): FileInterface {
    // Old and new file are the same.
    if ($oldFile && $newFile->uuid() === $oldFile->uuid()) {
      return $newFile;
    }

    $sourceUri = $newFile->getFileUri();
    $destinationUri = $this->getNewFileUriFromRename($newFile, 'favicon');

    if ($sourceUri === $destinationUri) {
      // The filename is already correct, no rename is needed.
      return $newFile;
    }

    return $this->fileRepository->move(
      $newFile,
      $destinationUri,
      FileSystemInterface::EXISTS_REPLACE
    );

  }

}
