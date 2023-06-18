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
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\tengstrom_general\HookHandlers\FormAlterHandlers\FormAlterHandlerInterface;

class TengstromConfigFormAlter implements FormAlterHandlerInterface {
  use UploadsFiles;
  use DependencySerializationTrait;

  protected const DEFAULT_PREVIEW_IMAGE_STYLE = 'original';

  protected ConfigFactoryInterface $configFactory;
  protected EntityStorageInterface $fileStorage;
  protected EntityStorageInterface $imageStyleStorage;
  protected FileRepositoryInterface $fileRepository;
  protected ThemeHandlerInterface $themeHandler;
  protected TranslationInterface $translator;
  protected UploadDimensions $uploadDimensions;

  public function __construct(
    ConfigFactoryInterface $configFactory,
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
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->imageStyleStorage = $entityTypeManager->getStorage('image_style');
    $this->fileRepository = $fileRepository;
    $this->themeHandler = $themeHandler;
    $this->translator = $translator;
    $this->uploadDimensions = UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['favicon']);
  }

  public function alter(array &$form, FormStateInterface $formState, string $formId): void {
    if (!$this->imageStyleStorage->load($this->getPreviewImageStyle())) {
      throw new \RuntimeException('The image style "' . $this->getPreviewImageStyle() . '" is missing!');
    }

    $config = $this->configFactory->get('tengstrom_config_favicon.settings');

    $form['favicon'] = [
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
      '#title'                => $this->translator->translate('Favicon'),
      '#theme' => 'image_widget',
      '#preview_image_style' => $this->getPreviewImageStyle(),
      '#weight' => 0,
    ];

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

  protected function getPreviewImageStyle(): string {
    return static::DEFAULT_PREVIEW_IMAGE_STYLE;
  }

}
