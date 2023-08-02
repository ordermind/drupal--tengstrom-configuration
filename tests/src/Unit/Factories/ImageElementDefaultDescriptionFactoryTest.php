<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\Factories;

use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\tengstrom_configuration\Factories\ImageElementDefaultDescriptionFactory;
use Drupal\tengstrom_configuration\ValueObjects\FileSize;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\Tests\UnitTestCase;

class ImageElementDefaultDescriptionFactoryTest extends UnitTestCase {

  public function testCreateThrowsExceptionOnEmptyExtensions(): void {
    $translator = new TranslationManager(new LanguageDefault(['id' => 'x-default']));

    $factory = new ImageElementDefaultDescriptionFactory($translator);

    $this->expectExceptionMessage('Please supply at least one allowed extension.');
    $factory->create([], new FileSize(20000));
  }

  /**
   * @dataProvider provideCreateCases
   */
  public function testCreateWithValidInput(
    string $expectedResult,
    array $allowedExtensions,
    FileSize $maxSize,
    ?UploadDimensions $optimalDimensions
  ): void {
    $translator = new TranslationManager(new LanguageDefault(['id' => 'x-default']));

    $factory = new ImageElementDefaultDescriptionFactory($translator);

    $this->assertSame($expectedResult, $factory->create($allowedExtensions, $maxSize, $optimalDimensions)->__toString());
  }

  public function provideCreateCases(): array {
    return [
      ['Allowed extensions: png jpg<br />Max size: 19.53kB', ['png', 'jpg'], new FileSize(20000), NULL],
      ['Allowed extensions: png gif<br />Optimal dimensions: 20px x 40px<br />Max size: 2.00MB', ['png', 'gif'], new FileSize(2097152), new UploadDimensions(20, 40)],
    ];
  }

}
