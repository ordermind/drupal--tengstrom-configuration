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
use Drupal\file\FileRepositoryInterface;
use Drupal\tengstrom_configuration\Concerns\UploadsFiles;
use Drupal\tengstrom_configuration\ElementBuilders\ImageElementBuilder;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Ordermind\DrupalTengstromShared\HookHandlers\FormAlterHandlerInterface;

class TengstromConfigFormAlter implements FormAlterHandlerInterface {
  use UploadsFiles;
  use DependencySerializationTrait;

  protected ConfigFactoryInterface $configFactory;
  protected ImageElementBuilder $elementBuilder;
  protected EntityStorageInterface $fileStorage;
  protected FileRepositoryInterface $fileRepository;
  protected ThemeHandlerInterface $themeHandler;
  protected TranslationInterface $translator;
  protected UploadDimensions $uploadDimensions;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    ImageElementBuilder $elementBuilder,
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
    $this->elementBuilder = $elementBuilder;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->fileRepository = $fileRepository;
    $this->themeHandler = $themeHandler;
    $this->translator = $translator;
    $this->uploadDimensions = UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['favicon']);
  }

  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    $config = $this->configFactory->get('tengstrom_config_favicon.settings');

    $this->elementBuilder
      ->withLabel($this->translator->translate('Favicon'))
      ->withPreviewImageStyle('config_thumbnail')
      ->withOptimalDimensions($this->uploadDimensions);

    if ($fileId = $this->getFileIdFromUuid($config->get('uuid'))) {
      $this->elementBuilder->withFileId($fileId);
    }

    $form['favicon'] = $this->elementBuilder->build();
    $form['#submit'][] = [$this, 'submit'];
  }

  public function submit(array &$form, FormStateInterface $form_state): void {
    $moduleConfig = $this->configFactory->getEditable('tengstrom_config_favicon.settings');
    $oldFile = $this->getOldFile($form_state, 'favicon');
    $newFile = $this->saveFileField($form_state, 'favicon');
    if ($newFile) {
      if (!$oldFile || $newFile->uuid() !== $oldFile->uuid()) {
        $newFile = $this->fileRepository->move(
          $newFile,
          $this->getNewFileUriFromRename($newFile, 'favicon'),
          FileSystemInterface::EXISTS_REPLACE
        );
      }

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

}
