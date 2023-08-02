<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ValueObjects;

class FileSize {
  protected int $bytes;

  public function __construct(int $bytes) {
    $this->bytes = $bytes;
  }

  public function getRawValueInBytes(): int {
    return $this->bytes;
  }

  public function getFormattedValue(int $decimals = 2): string {
    $sz = 'BKMGTP';
    $factor = floor((strlen((string) $this->bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $this->bytes / pow(1024, $factor)) . @$sz[$factor];
  }

}
