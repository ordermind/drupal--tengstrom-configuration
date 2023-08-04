<?php

declare(strict_types=1);

namespace Drupal\tengstrom_config_email_logo\HookHandlers\FormAlterHandlers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
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
  protected TranslationInterface $translator;
  protected UploadDimensions $uploadDimensions;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    ImageElementBuilder $elementBuilder,
    EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $translator,
    array $tengstromConfiguration
  ) {
    if (empty($tengstromConfiguration['upload_dimensions']['email_logo'])) {
      throw new \RuntimeException(
        'There must be an email_logo upload dimensions entry in the tengstrom config parameter (likely defined in sites/default/services.yml)'
      );
    }

    $this->configFactory = $configFactory;
    $this->elementBuilder = $elementBuilder;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->translator = $translator;
    $this->uploadDimensions = UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['email_logo']);
  }

  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    $config = $this->configFactory->get('tengstrom_config_email_logo.settings');

    $this->elementBuilder
      ->withLabel($this->translator->translate('Logo for e-mail'))
      ->withPreviewImageStyle('config_thumbnail')
      ->withOptimalDimensions($this->uploadDimensions)
      ->withWeight(-5);

    if ($fileId = $this->getFileIdFromUuid($config->get('uuid'))) {
      $this->elementBuilder->withFileId($fileId);
    }

    $form['email_logo'] = $this->elementBuilder->build();
    $form['#submit'][] = [$this, 'submit'];
  }

  public function submit(array &$form, FormStateInterface $form_state): void {
    $moduleConfig = $this->configFactory->getEditable('tengstrom_config_email_logo.settings');

    $newFile = $this->saveFileField($form_state, 'email_logo');
    if ($newFile) {
      $moduleConfig->set('uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('uuid', NULL);
    }
    $moduleConfig->save();
  }

  /**
   * {@inheritDoc}
   */
  protected function getFileStorage(): EntityStorageInterface {
    return $this->fileStorage;

  }

}
