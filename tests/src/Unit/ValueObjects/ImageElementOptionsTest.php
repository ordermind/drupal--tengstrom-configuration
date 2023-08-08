<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\ValueObjects;

use ChrisUllyott\FileSize;
use Drupal\tengstrom_configuration\ValueObjects\ImageElementOptions;
use PHPUnit\Framework\TestCase;

class ImageElementOptionsTest extends TestCase {

  public function testThrowsExceptionOnMissingAllowedExtensions(): void {
    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('Please supply at least one allowed extension.');
    new ImageElementOptions('Test Field', allowedExtensions: []);
  }

  public function testDefaultValues(): void {
    $options = new ImageElementOptions('Test Field');

    $this->assertSame('Test Field', $options->getLabel());
    $this->assertSame(NULL, $options->getDescriptionOverride());
    $this->assertSame(NULL, $options->getFileId());
    $this->assertSame(NULL, $options->getPreviewImageStyle());
    $this->assertSame(NULL, $options->getOptimalDimensions());
    $this->assertSame('public://', $options->getUploadLocation());
    $this->assertEquals(new FileSize('200 KB'), $options->getMaxSize());
    $this->assertSame(['gif', 'png', 'jpg', 'jpeg', 'webp'], $options->getAllowedExtensions());
    $this->assertSame(0, $options->getWeight());
  }

}
