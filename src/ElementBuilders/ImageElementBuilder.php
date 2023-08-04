<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ElementBuilders;

use ChrisUllyott\FileSize;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tengstrom_configuration\Factories\ImageElementDefaultDescriptionFactory;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\tengstrom_general\Repository\EntityRepositoryInterface;

/**
 * Use this class to create an image upload element in a type safe way that can
 * be serialized into a Drupal element array that can be used in a form.
 */
class ImageElementBuilder {
  protected TranslationInterface $translator;
  protected ImageElementDefaultDescriptionFactory $defaultDescriptionFactory;
  protected EntityRepositoryInterface $entityRepository;
  protected FileSystemInterface $fileSystem;

  protected string|TranslatableMarkup|NULL $label = NULL;
  protected string|TranslatableMarkup|NULL $description = NULL;
  protected ?UploadDimensions $optimalDimensions = NULL;
  protected ?int $fileId = NULL;
  protected string $uploadLocation = 'public://';
  /** @property string[] */
  protected array $allowedExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'];
  protected ?FileSize $maxSize;
  protected string $previewImageStyle = 'config_thumbnail';

  protected const REQUIRED_PROPERTIES = [
    'label',
  ];

  public function __construct(
    TranslationInterface $translator,
    ImageElementDefaultDescriptionFactory $defaultDescriptionFactory,
    EntityRepositoryInterface $entityRepository,
    FileSystemInterface $fileSystem
  ) {
    $this->translator = $translator;
    $this->defaultDescriptionFactory = $defaultDescriptionFactory;
    $this->entityRepository = $entityRepository;
    $this->fileSystem = $fileSystem;
  }

  protected function getDescription(): string|TranslatableMarkup {
    if ($this->description) {
      return $this->description;
    }

    return $this->defaultDescriptionFactory->create(
      $this->allowedExtensions,
      $this->getMaxSize(),
      $this->optimalDimensions
    );
  }

  protected function getDefaultMaxSize(): FileSize {
    return new FileSize('2 MB');
  }

  protected function getMaxSize(): FileSize {
    return $this->maxSize ?? $this->getDefaultMaxSize();
  }

  public function withLabel(string | TranslatableMarkup $label): static {
    $this->label = $label;

    return $this;
  }

  public function withDescription(string | TranslatableMarkup $description): static {
    $this->description = $description;

    return $this;
  }

  public function withOptimalDimensions(UploadDimensions $optimalDimensions): static {
    $this->optimalDimensions = $optimalDimensions;

    return $this;
  }

  public function withFileId(int $fileId): static {
    if (!$this->entityRepository->hasEntityIdForType('file', $fileId)) {
      throw new \RuntimeException("The file id \"{$fileId}\" does not exist in the database!");
    }

    $this->fileId = $fileId;

    return $this;
  }

  public function withUploadLocation(string $uploadLocation): static {
    if (!$this->fileSystem->prepareDirectory($uploadLocation, 0)) {
      throw new \RuntimeException("The upload location \"{$uploadLocation}\" does not exist or is not writable!");
    }

    $this->uploadLocation = $uploadLocation;

    return $this;
  }

  /**
   * @param string[] $allowedExtensions
   */
  public function withAllowedExtensions(array $allowedExtensions): static {
    if (!$allowedExtensions) {
      throw new \DomainException('Please supply at least one allowed extension.');
    }

    $this->allowedExtensions = $allowedExtensions;

    return $this;
  }

  public function withMaxSize(FileSize $maxSize): static {
    $this->maxSize = $maxSize;

    return $this;
  }

  public function withPreviewImageStyle(string $imageStyle): static {
    if (!$this->entityRepository->hasEntityIdForType('image_style', $imageStyle)) {
      throw new \RuntimeException("The image style \"{$imageStyle}\" does not exist in the database!");
    }

    $this->previewImageStyle = $imageStyle;

    return $this;
  }

  public function build(): array {
    foreach (static::REQUIRED_PROPERTIES as $propertyName) {
      if (!isset($this->{$propertyName})) {
        throw new \RuntimeException("The property \"{$propertyName}\" must be set before building the element.");
      }
    }

    return [
      '#type'                 => 'managed_file',
      '#upload_location'      => $this->uploadLocation,
      '#default_value' => array_filter(
        [$this->fileId]
      ),
      '#multiple'             => FALSE,
      '#description'          => $this->getDescription(),
      '#upload_validators'    => [
        'file_validate_is_image'      => [],
        'file_validate_extensions'    => implode(' ', $this->allowedExtensions),
        'file_validate_size'          => [$this->getMaxSize()->as('B')],
      ],
      '#title'                => $this->label,
      '#theme' => 'image_widget',
      '#preview_image_style' => $this->previewImageStyle,
      '#weight' => -5,
    ];
  }

}
