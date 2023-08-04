<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\ElementBuilders;

use ChrisUllyott\FileSize;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\tengstrom_configuration\ElementBuilders\ImageElementBuilder;
use Drupal\tengstrom_configuration\Factories\ImageElementDefaultDescriptionFactory;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\Tests\tengstrom_general\Unit\Fixtures\TestEntityRepository;
use Drupal\Tests\UnitTestCase;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Entity\DummyEntity;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ConfigEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ContentEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Factories\TestServiceContainerFactory;
use Prophecy\PhpUnit\ProphecyTrait;

class ImageElementBuilderTest extends UnitTestCase {
  use ProphecyTrait;

  protected TranslationManager $translator;
  protected TestEntityRepository $repository;
  protected FileSystemInterface $fileSystem;

  protected function setUp(): void {
    parent::setUp();

    $containerFactory = new TestServiceContainerFactory();
    $container = $containerFactory->createWithBasicServices();

    $imageStyleType = new EntityType([
      'id' => 'image_style',
      'class' => DummyEntity::class,
      'entity_keys' => [
        'id' => 'id',
        'label' => 'label',
      ],
    ]);

    $fileType = new EntityType([
      'id' => 'file',
      'class' => DummyEntity::class,
      'entity_keys' => [
        'id' => 'id',
        'label' => 'label',
      ],
    ]);

    $fileStorage = ContentEntityArrayStorage::createInstance($container, $fileType);
    $imageStyleStorage = ConfigEntityArrayStorage::createInstance($container, $imageStyleType);

    $mockEntityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $mockEntityTypeManager->getStorage($imageStyleType->id())->willReturn($imageStyleStorage);
    $mockEntityTypeManager->getDefinition($imageStyleType->id())->willReturn($imageStyleType);
    $mockEntityTypeManager->getStorage($fileType->id())->willReturn($fileStorage);
    $mockEntityTypeManager->getDefinition($fileType->id())->willReturn($fileType);
    $entityTypeManager = $mockEntityTypeManager->reveal();
    $container->set('entity_type.manager', $entityTypeManager);

    \Drupal::setContainer($container);

    $this->repository = new TestEntityRepository($entityTypeManager);
    $this->translator = $container->get('string_translation');

    $mockFileSystem = $this->prophesize(FileSystemInterface::class);
    $mockFileSystem->prepareDirectory('valid_location', 0)->willReturn(TRUE);
    $mockFileSystem->prepareDirectory('invalid_location', 0)->willReturn(FALSE);
    $this->fileSystem = $mockFileSystem->reveal();

    $fileStorage->create()->save();
    $imageStyleStorage->create(['id' => 'valid_style'])->save();
  }

  protected function getDefaultExpectedResult(): array {
    return [
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#default_value' => [],
      '#multiple' => FALSE,
      '#description' => $this->translator->translate(
        'Allowed extensions: @extensions<br />Max size: @size',
        [
          '@extensions' => 'gif png jpg jpeg webp',
          '@size' => '2 MB',
        ],
      ),
      '#upload_validators' => [
        'file_validate_is_image' => [],
        'file_validate_extensions' => ['gif png jpg jpeg webp'],
        'file_validate_size' => [2097152],
      ],
      '#title' => 'Test Field',
      '#theme' => 'image_widget',
      '#preview_image_style' => 'valid_style',
      '#weight' => 0,
    ];
  }

  protected function createElementBuilder(): ImageElementBuilder {
    return (new ImageElementBuilder(
      $this->translator,
      new ImageElementDefaultDescriptionFactory($this->translator),
      $this->repository,
      $this->fileSystem
    ))
      ->withLabel('Test Field')
      ->withPreviewImageStyle('valid_style');
  }

  public function testWithFileIdThrowsExceptionOnFileNotExist(): void {
    $builder = $this->createElementBuilder();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The file id "5" does not exist in the database!');
    $builder->withFileId(5);
  }

  public function testWithUploadLocationThrowsExceptionOnInvalidUploadLocation(): void {
    $builder = $this->createElementBuilder();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The upload location "invalid_location" does not exist or is not writable!');
    $builder->withUploadLocation('invalid_location');
  }

  public function testWithAllowedExtensionsThrowsExceptionOnMissingAllowedExtensions(): void {
    $builder = $this->createElementBuilder();

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('Please supply at least one allowed extension.');
    $builder->withAllowedExtensions([]);
  }

  public function testWithPreviewImageStyleThrowsExceptionOnInvalidImageStyle(): void {
    $builder = $this->createElementBuilder();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The image style "invalid_style" does not exist in the database!');
    $builder->withPreviewImageStyle('invalid_style');
  }

  public function testBuildThrowsExceptionOnMissingRequiredLabel(): void {
    $builder = new ImageElementBuilder(
      $this->translator,
      new ImageElementDefaultDescriptionFactory($this->translator),
      $this->repository,
      $this->fileSystem
    );

    $builder->withPreviewImageStyle('valid_style');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The property "label" must be set before building the element.');
    $builder->build();
  }

  public function testBuildThrowsExceptionOnMissingRequiredImageStyle(): void {
    $builder = new ImageElementBuilder(
      $this->translator,
      new ImageElementDefaultDescriptionFactory($this->translator),
      $this->repository,
      $this->fileSystem
    );

    $builder->withLabel('valid_style');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The property "previewImageStyle" must be set before building the element.');
    $builder->build();
  }

  public function testBuildWithBasicConfig(): void {
    $builder = $this->createElementBuilder();

    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();

    $this->assertEquals($expectedResult, $result);
  }

  /**
   * @dataProvider provideDescriptions
   */
  public function testBuildWithDescription(bool $useTranslation): void {
    $builder = $this->createElementBuilder();

    if ($useTranslation) {
      $description = $this->translator->translate('Test description');
    }
    else {
      $description = 'Test description';
    }

    $builder->withDescription($description);

    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#description'] = $description;

    $this->assertEquals($expectedResult, $result);
  }

  public function provideDescriptions(): array {
    return [
      [FALSE],
      [TRUE],
    ];
  }

  public function testBuildWithOptimalDimensions(): void {
    $builder = $this->createElementBuilder();

    $builder->withOptimalDimensions(UploadDimensions::fromArray(['width' => 35, 'height' => 78]));
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#description'] = $this->translator->translate(
      'Allowed extensions: @extensions<br />Optimal dimensions: @widthpx x @heightpx<br />Max size: @size',
      [
        '@extensions' => 'gif png jpg jpeg webp',
        '@size' => '2 MB',
        '@width' => 35,
        '@height' => 78,
      ],
    );

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithFileId(): void {
    $builder = $this->createElementBuilder();

    $builder->withFileId(1);
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#default_value'] = [1];

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithUploadLocation(): void {
    $builder = $this->createElementBuilder();

    $builder->withUploadLocation('valid_location');
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#upload_location'] = 'valid_location';

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithAllowedExtensions(): void {
    $builder = $this->createElementBuilder();

    $builder->withAllowedExtensions(['gif']);
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#description'] = $this->translator->translate(
      'Allowed extensions: @extensions<br />Max size: @size',
      [
        '@extensions' => 'gif',
        '@size' => '2 MB',
      ],
    );
    $expectedResult['#upload_validators']['file_validate_extensions'] = ['gif'];

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithMaxSize(): void {
    $builder = $this->createElementBuilder();

    $builder->withMaxSize(new FileSize('50 KB'));
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#description'] = $this->translator->translate(
      'Allowed extensions: @extensions<br />Max size: @size',
      [
        '@extensions' => 'gif png jpg jpeg webp',
        '@size' => '50 KB',
      ],
    );
    $expectedResult['#upload_validators']['file_validate_size'] = [51200];

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuilderWithWeight(): void {
    $builder = $this->createElementBuilder();

    $builder->withWeight(-5);
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#weight'] = -5;

    $this->assertEquals($expectedResult, $result);
  }

}
