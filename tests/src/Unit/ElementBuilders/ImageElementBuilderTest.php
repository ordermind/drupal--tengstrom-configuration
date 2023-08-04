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
use Drupal\Tests\tengstrom_general\Unit\Fixtures\TestConfigEntityRepository;
use Drupal\Tests\tengstrom_general\Unit\Fixtures\TestContentEntityRepository;
use Drupal\Tests\UnitTestCase;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Entity\DummyEntity;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ConfigEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ContentEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Factories\TestServiceContainerFactory;
use Prophecy\PhpUnit\ProphecyTrait;

class ImageElementBuilderTest extends UnitTestCase {
  use ProphecyTrait;

  protected const CONTENT_REPOSITORY = 'content_repository';
  protected const CONFIG_REPOSITORY = 'config_repository';

  protected TranslationManager $translator;
  protected EntityType $entityType;
  protected ContentEntityArrayStorage $contentEntityStorage;
  protected ConfigEntityArrayStorage $configEntityStorage;

  protected function setUp(): void {
    parent::setUp();

    $containerFactory = new TestServiceContainerFactory();
    $container = $containerFactory->createWithBasicServices();
    \Drupal::setContainer($container);

    $this->translator = $container->get('string_translation');

    $this->entityType = new EntityType([
      'id' => 'test_type',
      'class' => DummyEntity::class,
      'entity_keys' => [
        'id' => 'id',
        'label' => 'label',
      ],
    ]);

    $this->contentEntityStorage = ContentEntityArrayStorage::createInstance($container, $this->entityType);
    $this->configEntityStorage = ConfigEntityArrayStorage::createInstance($container, $this->entityType);
  }

  protected function createBuilder(string $repositoryType): ImageElementBuilder {

    if (static::CONFIG_REPOSITORY === $repositoryType) {
      $repository = new TestConfigEntityRepository($this->configEntityStorage);
    }
    else {
      $repository = new TestContentEntityRepository($this->contentEntityStorage);
    }

    $mockFileSystem = $this->prophesize(FileSystemInterface::class);
    $mockFileSystem->prepareDirectory('valid_location', 0)->willReturn(TRUE);
    $mockFileSystem->prepareDirectory('invalid_location', 0)->willReturn(FALSE);
    $fileSystem = $mockFileSystem->reveal();

    return new ImageElementBuilder(
      $this->translator,
      new ImageElementDefaultDescriptionFactory($this->translator),
      $repository,
      $fileSystem
    );
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
        'file_validate_extensions' => 'gif png jpg jpeg webp',
        'file_validate_size' => [2097152],
      ],
      '#title' => 'Test Field',
      '#theme' => 'image_widget',
      '#preview_image_style' => 'config_thumbnail',
      '#weight' => -5,
    ];
  }

  public function testWithFileIdThrowsExceptionOnFileNotExist(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The file id "5" does not exist in the database!');
    $builder->withFileId(5);
  }

  public function testWithUploadLocationThrowsExceptionOnInvalidUploadLocation(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The upload location "invalid_location" does not exist or is not writable!');
    $builder->withUploadLocation('invalid_location');
  }

  public function testWithAllowedExtensionsThrowsExceptionOnMissingAllowedExtensions(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY);

    $this->expectException(\DomainException::class);
    $this->expectExceptionMessage('Please supply at least one allowed extension.');
    $builder->withAllowedExtensions([]);
  }

  public function testWithPreviewImageStyleThrowsExceptionOnInvalidImageStyle(): void {
    $builder = $this->createBuilder(static::CONFIG_REPOSITORY);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The image style "invalid_style" does not exist in the database!');
    $builder->withPreviewImageStyle('invalid_style');
  }

  public function testBuildThrowsExceptionOnMissingRequiredFields(): void {

    $builder = $this->createBuilder(static::CONTENT_REPOSITORY);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The property "label" must be set before building the element.');
    $builder->build();
  }

  public function testBuildWithOnlyLabel(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();

    $this->assertEquals($expectedResult, $result);
  }

  /**
   * @dataProvider provideDescriptions
   */
  public function testBuildWithDescription(bool $useTranslation): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

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
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

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
    $mockEntityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $mockEntityTypeManager->getStorage($this->entityType->id())->willReturn($this->contentEntityStorage);
    $mockEntityTypeManager->getDefinition($this->entityType->id())->willReturn($this->entityType);
    $entityTypeManager = $mockEntityTypeManager->reveal();

    $container = \Drupal::getContainer();
    $container->set('entity_type.manager', $entityTypeManager);

    $this->contentEntityStorage->create(['id' => 1])->save();

    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

    $builder->withFileId(1);
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#default_value'] = [1];

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithUploadLocation(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

    $builder->withUploadLocation('valid_location');
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#upload_location'] = 'valid_location';

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithAllowedExtensions(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

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
    $expectedResult['#upload_validators']['file_validate_extensions'] = 'gif';

    $this->assertEquals($expectedResult, $result);
  }

  public function testBuildWithMaxSize(): void {
    $builder = $this->createBuilder(static::CONTENT_REPOSITORY)->withLabel('Test Field');

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

  public function testBuildWithPreviewImageStyle(): void {
    $mockEntityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $mockEntityTypeManager->getStorage($this->entityType->id())->willReturn($this->configEntityStorage);
    $mockEntityTypeManager->getDefinition($this->entityType->id())->willReturn($this->entityType);
    $entityTypeManager = $mockEntityTypeManager->reveal();

    $container = \Drupal::getContainer();
    $container->set('entity_type.manager', $entityTypeManager);

    $this->configEntityStorage->create(['id' => 'valid_style'])->save();

    $builder = $this->createBuilder(static::CONFIG_REPOSITORY)->withLabel('Test Field');

    $builder->withPreviewImageStyle('valid_style');
    $result = $builder->build();
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#preview_image_style'] = 'valid_style';

    $this->assertEquals($expectedResult, $result);
  }

}
