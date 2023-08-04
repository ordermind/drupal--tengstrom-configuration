<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\ElementBuilders;

use Drupal\Core\Cache\MemoryCache\MemoryCache;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\tengstrom_configuration\ElementBuilders\ImageElementBuilder;
use Drupal\tengstrom_configuration\Factories\ImageElementDefaultDescriptionFactory;
use Drupal\Tests\tengstrom_general\Unit\Fixtures\TestEntityRepository;
use Drupal\Tests\UnitTestCase;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Entity\DummyEntity;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\EntityArrayStorage;

class ImageElementBuilderTest extends UnitTestCase {

  protected function createBuilder(): ImageElementBuilder {
    $translator = new TranslationManager(new LanguageDefault(['id' => 'x-default']));
    $entityType = new EntityType([
      'id' => 'test_type',
      'class' => DummyEntity::class,
      'entity_keys' => [
        'id' => 'id',
        'label' => 'label',
      ],
    ]);
    $fileRepository = new TestEntityRepository(new EntityArrayStorage($entityType, new MemoryCache()));

    return new ImageElementBuilder(
      $translator,
      new ImageElementDefaultDescriptionFactory($translator),
      $fileRepository
    );
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
