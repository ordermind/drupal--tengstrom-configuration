<?php

declare(strict_types=1);

namespace Drupal\tengstrom_config_logo\HookHandlers\FormAlterHandlers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
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
  protected ThemeHandlerInterface $themeHandler;
  protected TranslationInterface $translator;
  protected UploadDimensions $uploadDimensions;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    ImageElementFactory $elementFactory,
    EntityTypeManagerInterface $entityTypeManager,
    ThemeHandlerInterface $themeHandler,
    TranslationInterface $translator,
    array $tengstromConfiguration
  ) {
    if (empty($tengstromConfiguration['upload_dimensions']['logo'])) {
      throw new \RuntimeException(
        'There must be a logo upload dimensions entry in the tengstrom config parameter (likely defined in sites/default/services.yml)'
      );
    }

    $this->configFactory = $configFactory;
    $this->elementFactory = $elementFactory;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->themeHandler = $themeHandler;
    $this->translator = $translator;
    $this->uploadDimensions = UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['logo']);
  }

  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    $config = $this->configFactory->get('tengstrom_config_logo.settings');

    $options = new ImageElementOptions(
      label: $this->translator->translate('Logo'),
      previewImageStyle: 'config_thumbnail',
      fileId: $this->getFileIdFromUuid($config->get('uuid')) ?? NULL,
      optimalDimensions: $this->uploadDimensions,
      weight: -10
    );

    $form['logo'] = $this->elementFactory->create($options);
    $form['#submit'][] = [$this, 'submit'];
  }

  public function submit(array &$form, FormStateInterface $form_state): void {
    $moduleConfig = $this->configFactory->getEditable('tengstrom_config_logo.settings');
    $newFile = $this->saveFileField($form_state, 'logo');
    if ($newFile) {
      $moduleConfig->set('uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('uuid', NULL);
    }
    $moduleConfig->save();

    $themeConfig = $this->configFactory->getEditable($this->themeHandler->getDefault() . '.settings');
    if ($newFile) {
      $themeConfig->set('logo.use_default', FALSE);
      $themeConfig->set('logo.path', $newFile->getFileUri());
    }
    else {
      $themeConfig->set('logo.use_default', TRUE);
      $themeConfig->set('logo.path', '');
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
