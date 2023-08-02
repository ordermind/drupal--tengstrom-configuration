<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\ValueObjects;

use Drupal\tengstrom_configuration\ValueObjects\FileSize;
use Drupal\Tests\UnitTestCase;

class FileSizeTest extends UnitTestCase {

  /**
   * @dataProvider provideFileSizeCases
   */
  public function testFileSize(string $expectedFormattedValue, int $decimals, int $bytes): void {
    $fileSize = new FileSize($bytes);

    $this->assertSame($bytes, $fileSize->getRawValueInBytes());
    $this->assertSame($expectedFormattedValue, $fileSize->getFormattedValue($decimals));
  }

  public function provideFileSizeCases(): array {
    return [
      ['200B', 0, 200],
      ['200.00B', 2, 200],
      ['2kB', 0, 2000],
      ['1.95kB', 2, 2000],
      ['2.00kB', 2, 2048],
      ['488.28kB', 2, 500000],
      ['512.00kB', 2, 524288],
      ['2.00MB', 2, 2097152],
    ];
  }

}
