<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ValueObjects;

use ChrisUllyott\FileSize;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class ImageElementOptions {
  protected TranslatableMarkup|string $label;
  protected string $previewImageStyle;
  protected ?int $fileId;
  protected TranslatableMarkup|string|NULL $descriptionOverride;
  protected ?UploadDimensions $optimalDimensions;
  protected string $uploadLocation;
  protected FileSize $maxSize;
  /** @property string[] */
  protected array $allowedExtensions;
  protected int $weight;

  public function __construct(
    TranslatableMarkup|string $label,
    string $previewImageStyle,
    ?int $fileId = NULL,
    TranslatableMarkup|string|NULL $descriptionOverride = NULL,
    ?UploadDimensions $optimalDimensions = NULL,
    string $maxSize = '200 KB',
    string $uploadLocation = 'public://',
    array $allowedExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'],
    int $weight = 0
  ) {
    if (!$allowedExtensions) {
      throw new \DomainException('Please supply at least one allowed extension.');
    }

    $this->label = $label;
    $this->previewImageStyle = $previewImageStyle;
    $this->fileId = $fileId;
    $this->descriptionOverride = $descriptionOverride;
    $this->optimalDimensions = $optimalDimensions;
    $this->maxSize = new FileSize($maxSize);
    $this->uploadLocation = $uploadLocation;
    $this->allowedExtensions = $allowedExtensions;
    $this->weight = $weight;
  }

  public function getLabel(): TranslatableMarkup|string {
    return $this->label;
  }

  public function getPreviewImageStyle(): string {
    return $this->previewImageStyle;
  }

  public function getFileId(): ?int {
    return $this->fileId;
  }

  public function getDescriptionOverride(): TranslatableMarkup|string|NULL {
    return $this->descriptionOverride;
  }

  public function getOptimalDimensions(): ?UploadDimensions {
    return $this->optimalDimensions;
  }

  public function getUploadLocation(): string {
    return $this->uploadLocation;
  }

  public function getMaxSize(): FileSize {
    return $this->maxSize;
  }

  /**
   * @return string[]
   */
  public function getAllowedExtensions(): array {
    return $this->allowedExtensions;
  }

  public function getWeight(): int {
    return $this->weight;
  }

}
