<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ValueObjects;

use Ordermind\Helpers\ValueObject\Integer\PositiveInteger;

class UploadDimensions {
  protected PositiveInteger $width;
  protected PositiveInteger $height;

  public function __construct(PositiveInteger $width, PositiveInteger $height) {
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

    return new static(new PositiveInteger((int) $values['width']), new PositiveInteger((int) $values['height']));
  }

  public function getWidth(): PositiveInteger {
    return $this->width;
  }

  public function getHeight(): PositiveInteger {
    return $this->height;
  }

}
