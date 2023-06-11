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
    return new static($values['width'], $values['height']);
  }

  public function getWidth(): int {
    return $this->width;
  }

  public function getHeight(): int {
    return $this->height;
  }

}
