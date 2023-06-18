<?php

declare(strict_types=1);

namespace Drupal\tengstrom_config_email_logo\HookHandlers\FormAlterHandlers;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tengstrom_configuration\Concerns\UploadsFiles;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\tengstrom_general\HookHandlers\FormAlterHandlers\FormAlterHandlerInterface;

class TengstromConfigFormAlter implements FormAlterHandlerInterface {
  use UploadsFiles;
  use DependencySerializationTrait;

  protected ConfigFactoryInterface $configFactory;
  protected EntityStorageInterface $fileStorage;
  protected ThemeHandlerInterface $themeHandler;
  protected TranslationInterface $translator;
  protected UploadDimensions $uploadDimensions;

  public function __construct(
    ConfigFactoryInterface $configFactory,
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
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->themeHandler = $themeHandler;
    $this->translator = $translator;
    $this->uploadDimensions = UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['logo']);
  }

  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    $config = $this->configFactory->get('tengstrom_config_email_logo.settings');

    $form['logo_email'] = [
      '#type'                 => 'managed_file',
      '#upload_location'      => 'public://',
      '#default_value' => array_filter(
        [$this->getFileIdFromUuid($config->get('uuid'))]
      ),
      '#multiple'             => FALSE,
      '#description'          => $this->translator->translate(
        'Allowed extensions: @extensions<br />Optimal dimensions: @widthpx x @heightpx',
        [
          '@extensions' => 'gif png jpg jpeg webp',
          '@width' => $this->uploadDimensions->getWidth(),
          '@height' => $this->uploadDimensions->getHeight(),
        ]
      ),
      '#upload_validators'    => [
        'file_validate_is_image'      => [],
        'file_validate_extensions'    => ['gif png jpg jpeg webp'],
        'file_validate_size'          => [25600000],
      ],
      '#title'                => $this->translator->translate('Logo for e-mail'),
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
      '#weight' => -5,
    ];

    $form['#submit'][] = [$this, 'submit'];
  }

  public function submit(array &$form, FormStateInterface $form_state): void {
    $moduleConfig = $this->configFactory->getEditable('tengstrom_config_email_logo.settings');

    $newFile = $this->saveFileField($form_state, 'logo_email');
    if ($newFile) {
      $moduleConfig->set('uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('uuid', NULL);
    }
    $moduleConfig->save();
  }

  protected function getFileStorage(): EntityStorageInterface {
    return $this->fileStorage;

  }

}
