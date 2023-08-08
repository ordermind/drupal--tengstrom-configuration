<?php

declare(strict_types=1);

namespace Drupal\tengstrom_config_favicon\HookHandlers\FormAlterHandlers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileStorage;
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
      fileId: $this->getFileIdFromUuid($config->get('uuid')) ?? NULL,
      previewImageStyle: 'config_thumbnail',
      optimalDimensions: $this->uploadDimensions,
      maxSize: '2 KB'
    );

    $form['favicon'] = $this->elementFactory->create($options);
    $form['#submit'][] = [$this, 'submit'];
  }

  public function submit(array &$form, FormStateInterface $form_state): void {
    $moduleConfig = $this->configFactory->getEditable('tengstrom_config_favicon.settings');
    $themeConfig = $this->configFactory->getEditable($this->themeHandler->getDefault() . '.settings');
    $oldFileId = $this->getOldFileId($form_state, 'favicon');
    $newFileId = $this->getNewFileId($form_state, 'favicon');
    $newFile = $this->saveFileField($oldFileId, $newFileId);

    $this->updateModuleConfig($moduleConfig, $newFile);
    $this->updateThemeConfig($themeConfig, $newFile, 'favicon');
  }

  /**
   * {@inheritDoc}
   */
  protected function getFileStorage(): FileStorage {
    return $this->fileStorage;
  }

}
