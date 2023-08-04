<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\ValueObjects;

use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\Tests\UnitTestCase;

class UploadDimensionsTest extends UnitTestCase {

  /**
   * @test
   *
   * phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
   */
  public function fromArray_throws_exception_on_missing_width(): void {
    $values = ['height' => 20];

    $this->expectExceptionMessage('Width is required in upload dimensions');
    UploadDimensions::fromArray($values);
  }

  /**
   * @test
   *
   * phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
   */
  public function fromArray_throws_exception_on_missing_height(): void {
    $values = ['width' => 20];

    $this->expectExceptionMessage('Height is required in upload dimensions');
    UploadDimensions::fromArray($values);
  }

  /**
   * @test
   *
   * phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
   */
  public function fromArray_creates_object_for_valid_input(): void {
    $values = ['width' => 20, 'height' => 40];

    $dimensions = UploadDimensions::fromArray($values);
    $this->assertSame($values['width'], $dimensions->getWidth()->getValue());
    $this->assertSame($values['height'], $dimensions->getHeight()->getValue());
  }

}
