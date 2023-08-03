<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\ElementBuilders;

use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\tengstrom_configuration\ElementBuilders\ImageElementBuilder;
use Drupal\tengstrom_configuration\Factories\ImageElementDefaultDescriptionFactory;
use Drupal\Tests\UnitTestCase;

class ImageElementBuilderTest extends UnitTestCase {

  protected function createBuilder(): ImageElementBuilder {
    $translator = new TranslationManager(new LanguageDefault(['id' => 'x-default']));

    return new ImageElementBuilder($translator, new ImageElementDefaultDescriptionFactory($translator));
  }

  public function testBuildThrowsExceptionOnMissingRequiredFields(): void {
    $builder = $this->createBuilder();

    $this->expectExceptionMessage('The property "label" must be set before building the element.');
    $builder->build();
  }

  public function testBuildThrowsExceptionOnMissingAllowedExtensions(): void {
    $builder = $this->createBuilder()->withLabel('Image field');

    $this->expectExceptionMessage('Please supply at least one allowed extension.');
    $builder->withAllowedExtensions([]);
  }

}
