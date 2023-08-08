<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ValueObjects;

use ChrisUllyott\FileSize;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class ImageElementOptions {
  protected TranslatableMarkup|string $label;
  protected TranslatableMarkup|string|NULL $descriptionOverride;
  protected ?int $fileId;
  protected ?string $previewImageStyle;
  protected ?UploadDimensions $optimalDimensions;
  protected string $uploadLocation;
  protected FileSize $maxSize;
  /** @property string[] */
  protected array $allowedExtensions;
  protected int $weight;

  public function __construct(
    TranslatableMarkup|string $label,
    TranslatableMarkup|string|NULL $descriptionOverride = NULL,
    ?int $fileId = NULL,
    ?string $previewImageStyle = NULL,
    ?UploadDimensions $optimalDimensions = NULL,
    string $uploadLocation = 'public://',
    string $maxSize = '200 KB',
    array $allowedExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'],
    int $weight = 0
  ) {
    if (!$allowedExtensions) {
      throw new \DomainException('Please supply at least one allowed extension.');
    }

    $this->label = $label;
    $this->descriptionOverride = $descriptionOverride;
    $this->fileId = $fileId;
    $this->previewImageStyle = $previewImageStyle;
    $this->optimalDimensions = $optimalDimensions;
    $this->maxSize = new FileSize($maxSize);
    $this->uploadLocation = $uploadLocation;
    $this->allowedExtensions = $allowedExtensions;
    $this->weight = $weight;
  }

  public function getLabel(): TranslatableMarkup|string {
    return $this->label;
  }

  public function getDescriptionOverride(): TranslatableMarkup|string|NULL {
    return $this->descriptionOverride;
  }

  public function getFileId(): ?int {
    return $this->fileId;
  }

  public function getPreviewImageStyle(): ?string {
    return $this->previewImageStyle;
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
