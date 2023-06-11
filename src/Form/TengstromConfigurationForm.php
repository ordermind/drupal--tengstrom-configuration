<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TengstromConfigurationForm extends ConfigFormBase {
  protected ThemeHandlerInterface $themeHandler;
  protected EntityStorageInterface $fileStorage;
  protected FileRepositoryInterface $fileRepository;
  protected UploadDimensions $uploadDimensionsLogo;
  protected UploadDimensions $uploadDimensionsEmailLogo;
  protected UploadDimensions $uploadDimensionsFavicon;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    ThemeHandlerInterface $themeHandler,
    EntityStorageInterface $fileStorage,
    FileRepositoryInterface $fileRepository,
    UploadDimensions $uploadDimensionsLogo,
    UploadDimensions $uploadDimensionsEmailLogo,
    UploadDimensions $uploadDimensionsFavicon
  ) {
    parent::__construct($configFactory);

    $this->themeHandler = $themeHandler;
    $this->fileStorage = $fileStorage;
    $this->fileRepository = $fileRepository;
    $this->uploadDimensionsLogo = $uploadDimensionsLogo;
    $this->uploadDimensionsEmailLogo = $uploadDimensionsEmailLogo;
    $this->uploadDimensionsFavicon = $uploadDimensionsFavicon;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $tengstromConfiguration = $container->getParameter('tengstrom_configuration');

    return new static(
      $container->get('config.factory'),
      $container->get('theme_handler'),
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('file.repository'),
      UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['logo']),
      UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['email_logo']),
      UploadDimensions::fromArray($tengstromConfiguration['upload_dimensions']['favicon']),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'tengstrom_configuration_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('tengstrom_configuration.settings');

    $form = parent::buildForm($form, $form_state);

    $form['logo'] = [
      '#type'                 => 'managed_file',
      '#upload_location'      => 'public://',
      '#default_value' => array_filter(
        [$this->getFileIdFromUuid($config->get('logo_uuid'))]
      ),
      '#multiple'             => FALSE,
      '#description'          => $this->t(
        'Allowed extensions: @extensions<br />Optimal dimensions: @widthpx x @heightpx',
        [
          '@extensions' => 'gif png jpg jpeg webp',
          '@width' => $this->uploadDimensionsLogo->getWidth(),
          '@height' => $this->uploadDimensionsLogo->getHeight(),
        ]
      ),
      '#upload_validators'    => [
        'file_validate_is_image'      => [],
        'file_validate_extensions'    => ['gif png jpg jpeg webp'],
        'file_validate_size'          => [25600000],
      ],
      '#title'                => $this->t('Logo'),
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
    ];

    $form['logo_email'] = [
      '#type'                 => 'managed_file',
      '#upload_location'      => 'public://',
      '#default_value' => array_filter(
        [$this->getFileIdFromUuid($config->get('logo_email_uuid'))]
      ),
      '#multiple'             => FALSE,
      '#description'          => $this->t(
        'Allowed extensions: @extensions<br />Optimal dimensions: @widthpx x @heightpx',
        [
          '@extensions' => 'gif png jpg jpeg webp',
          '@width' => $this->uploadDimensionsEmailLogo->getWidth(),
          '@height' => $this->uploadDimensionsEmailLogo->getHeight(),
        ]
      ),
      '#upload_validators'    => [
        'file_validate_is_image'      => [],
        'file_validate_extensions'    => ['gif png jpg jpeg webp'],
        'file_validate_size'          => [25600000],
      ],
      '#title'                => $this->t('Logo for e-mail'),
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
    ];

    $form['favicon'] = [
      '#type'                 => 'managed_file',
      '#upload_location'      => 'public://',
      '#default_value' => array_filter(
        [$this->getFileIdFromUuid($config->get('favicon_uuid'))]
      ),
      '#multiple'             => FALSE,
      '#description'          => $this->t(
        'Allowed extensions: @extensions<br />Optimal dimensions: @widthpx x @heightpx',
        [
          '@extensions' => 'ico gif png jpg jpeg',
          '@width' => $this->uploadDimensionsFavicon->getWidth(),
          '@height' => $this->uploadDimensionsFavicon->getHeight(),
        ]
      ),
      '#upload_validators'    => [
        'file_validate_is_image'      => [],
        'file_validate_extensions'    => ['ico gif png jpg jpeg'],
        'file_validate_size'          => [25600000],
      ],
      '#title'                => $this->t('Favicon'),
      '#theme' => 'image_widget',
      '#preview_image_style' => 'original',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->submitLogo($form_state);
    $this->submitLogoEmail($form_state);
    $this->submitFavicon($form_state);

    parent::submitForm($form, $form_state);
  }

  protected function getEditableConfigNames(): array {
    return [
      'tengstrom_configuration.settings',
      $this->themeHandler->getDefault() . '.settings',
    ];
  }

  protected function submitLogo(FormStateInterface $form_state): void {
    $moduleConfig = $this->config('tengstrom_configuration.settings');
    $newFile = $this->saveFileField($form_state, 'logo');
    if ($newFile) {
      $moduleConfig->set('logo_uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('logo_uuid', NULL);
    }
    $moduleConfig->save();

    $themeConfig = $this->config($this->themeHandler->getDefault() . '.settings');
    if ($newFile) {
      $themeConfig->set('logo.use_default', FALSE);
      $themeConfig->set('logo.path', $newFile->getFileUri());
    }
    else {
      $themeConfig->set('logo.use_default', TRUE);
      $themeConfig->set('logo.path', NULL);
    }
    $themeConfig->save();
  }

  protected function submitLogoEmail(FormStateInterface $form_state): void {
    $moduleConfig = $this->config('tengstrom_configuration.settings');
    $newFile = $this->saveFileField($form_state, 'logo_email');
    if ($newFile) {
      $moduleConfig->set('logo_email_uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('logo_email_uuid', NULL);
    }
    $moduleConfig->save();
  }

  protected function submitFavicon(FormStateInterface $form_state): void {
    $moduleConfig = $this->config('tengstrom_configuration.settings');
    $oldFile = $this->getOldFile($form_state, 'favicon');
    $newFile = $this->saveFileField($form_state, 'favicon');
    if ($newFile) {
      if ($oldFile && $newFile->uuid() !== $oldFile->uuid()) {
        $newFile = $this->fileRepository->move(
          $newFile,
          $this->getNewFileUriFromRename($newFile, 'favicon'),
          FileSystemInterface::EXISTS_REPLACE
        );
      }

      $moduleConfig->set('favicon_uuid', $newFile->uuid());
    }
    else {
      $moduleConfig->set('favicon_uuid', NULL);
    }
    $moduleConfig->save();

    $themeConfig = $this->config($this->themeHandler->getDefault() . '.settings');
    if ($newFile) {
      $themeConfig->set('favicon.use_default', FALSE);
      $themeConfig->set('favicon.path', $newFile->getFileUri());
    }
    else {
      $themeConfig->set('favicon.use_default', TRUE);
      $themeConfig->set('favicon.path', NULL);
    }
    $themeConfig->save();
  }

  protected function saveFileField(FormStateInterface $form_state, string $fieldName): ?File {
    $fileData = $form_state->getValue($fieldName);
    $oldFile = $this->getOldFile($form_state, $fieldName);

    if (!$fileData) {
      if ($oldFile) {
        $this->fileStorage->delete([$oldFile]);
      }

      return NULL;
    }

    $newFile = File::load($fileData[0]);
    $newFile->setPermanent();
    $newFile->save();

    // File has been changed, delete the old one.
    if ($oldFile && $newFile->uuid() !== $oldFile->uuid()) {
      $this->fileStorage->delete([$oldFile]);
    }

    return $newFile;
  }

  protected function getFileIdFromUuid(?string $uuid): ?int {
    if (!$uuid) {
      return NULL;
    }

    $foundFiles = $this->fileStorage->loadByProperties(['uuid' => $uuid]);
    if ($newFile = reset($foundFiles)) {
      return $newFile->id();
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

    return $this->fileStorage->load($oldFileId);
  }

}
