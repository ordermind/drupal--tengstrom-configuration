<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\Factories;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tengstrom_configuration\ValueObjects\ImageElementOptions;
use Drupal\tengstrom_general\Repository\EntityRepositoryInterface;

class ImageElementFactory {
  protected TranslationInterface $translator;
  protected EntityRepositoryInterface $entityRepository;
  protected FileSystemInterface $fileSystem;

  public function __construct(
    TranslationInterface $translator,
    EntityRepositoryInterface $entityRepository,
    FileSystemInterface $fileSystem,
  ) {
    $this->translator = $translator;
    $this->entityRepository = $entityRepository;
    $this->fileSystem = $fileSystem;
  }

  public function create(ImageElementOptions $options): array {
    $this->validate($options);

    $element = [
      '#type'                 => 'managed_file',
      '#upload_location'      => $options->getUploadLocation(),
      '#default_value' => array_filter(
        [$options->getFileId()]
      ),
      '#multiple'             => FALSE,
      '#description'          => $this->createDescription($options),
      '#upload_validators'    => [
        'FileIsImage'      => [],
        'FileExtension'    => ['extensions' => implode(' ', $options->getAllowedExtensions())],
        'FileSizeLimit'          => ['fileLimit' => $options->getMaxSize()->as('B')],
      ],
      '#title'                => $options->getLabel(),
      '#theme' => 'image_widget',
      '#trim_file_link' => 17,
      '#weight' => $options->getWeight(),
    ];

    if ($options->getPreviewImageStyle()) {
      $element['#preview_image_style'] = $options->getPreviewImageStyle();
    }

    return $element;
  }

  protected function validate(ImageElementOptions $options): void {
    $fileId = $options->getFileId();
    if ($fileId && !$this->entityRepository->hasEntityIdForType('file', $fileId)) {
      throw new \RuntimeException("The file id \"{$fileId}\" does not exist in the database!");
    }

    $uploadLocation = $options->getUploadLocation();
    if (!$this->fileSystem->prepareDirectory($uploadLocation, 0)) {
      throw new \RuntimeException("The upload location \"{$uploadLocation}\" does not exist or is not writable!");
    }

    $imageStyle = $options->getPreviewImageStyle();
    if ($imageStyle && !$this->entityRepository->hasEntityIdForType('image_style', $imageStyle)) {
      throw new \RuntimeException("The image style \"{$imageStyle}\" does not exist in the database!");
    }
  }

  protected function createDescription(ImageElementOptions $options): TranslatableMarkup|string {
    if ($options->getDescriptionOverride() !== NULL) {
      return $options->getDescriptionOverride();
    }

    $translationArguments = [
      '@extensions' => implode(' ', $options->getAllowedExtensions()),
      '@size' => $options->getMaxSize()->asAuto(),
    ];

    $translationString = 'Allowed extensions: @extensions';
    if ($optimalDimensions = $options->getOptimalDimensions()) {
      $translationString .= '<br />Optimal dimensions: @widthpx x @heightpx';
      $translationArguments['@width'] = $optimalDimensions->getWidth()->getValue();
      $translationArguments['@height'] = $optimalDimensions->getHeight()->getValue();
    }
    $translationString .= '<br />Max size: @size';

    return $this->translator->translate($translationString, $translationArguments);
  }

}
