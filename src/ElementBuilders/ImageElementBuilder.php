<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ElementBuilders;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tengstrom_configuration\ValueObjects\FileSize;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Twig\Error\RuntimeError;

/**
 * Use this class to create an image upload element in a type safe way that can
 * be serialized into a Drupal element array that can be used in a form.
 */
class ImageElementBuilder {
  protected TranslationInterface $translator;
  protected ImageElementDefaultDescriptionFactory $defaultDescriptionFactory;

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

  public function __construct(TranslationInterface $translator, ImageElementDefaultDescriptionFactory $defaultDescriptionFactory) {
    $this->translator = $translator;
    $this->defaultDescriptionFactory = $defaultDescriptionFactory;
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
    // 2 MB
    return new FileSize(2 * pow(2, 20));
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
    $this->fileId = $fileId;

    return $this;
  }

  public function withUploadLocation(string $uploadLocation): static {
    $this->uploadLocation = $uploadLocation;

    return $this;
  }

  /**
   * @param string[] $allowedExtensions
   */
  public function withAllowedExtensions(array $allowedExtensions): static {
    $this->allowedExtensions = $allowedExtensions;

    return $this;
  }

  public function withMaxSize(FileSize $maxSize): static {
    $this->maxSize = $maxSize;

    return $this;
  }

  public function withPreviewImageStyle(string $imageStyle): static {
    $this->previewImageStyle = $imageStyle;

    return $this;
  }

  public function build(): array {
    foreach (static::REQUIRED_PROPERTIES as $propertyName) {
      if (!isset($this->{$propertyName})) {
        throw new RuntimeError("The property \"{$propertyName}\" must be set before building the element.");
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
        'file_validate_size'          => [$this->getMaxSize()->getRawValueInBytes()],
      ],
      '#title'                => $this->label,
      '#theme' => 'image_widget',
      '#preview_image_style' => $this->previewImageStyle,
      '#weight' => -5,
    ];
  }

}