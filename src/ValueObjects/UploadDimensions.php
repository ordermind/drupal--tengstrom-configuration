<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ValueObjects;

class UploadDimensions {
  protected int $width;
  protected int $height;

  public function __construct(int $width, int $height) {
    $this->width = $width;
    $this->height = $height;
  }

  public static function fromArray(array $values): static {
    if (empty($values['width'])) {
      throw new \InvalidArgumentException('Width is required in upload dimensions');
    }
    if (empty($values['height'])) {
      throw new \InvalidArgumentException('Height is required in upload dimensions');
    }

    return new static($values['width'], $values['height']);
  }

  public function getWidth(): int {
    return $this->width;
  }

  public function getHeight(): int {
    return $this->height;
  }

}
