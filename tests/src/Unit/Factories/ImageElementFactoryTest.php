<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\Factories;

use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\tengstrom_configuration\Factories\ImageElementFactory;
use Drupal\tengstrom_configuration\ValueObjects\ImageElementOptions;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;
use Drupal\Tests\tengstrom_general\Unit\Fixtures\TestEntityRepository;
use Drupal\Tests\UnitTestCase;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Entity\DummyEntity;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ConfigEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ContentEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Factories\TestServiceContainerFactory;
use Prophecy\PhpUnit\ProphecyTrait;

class ImageElementFactoryTest extends UnitTestCase {
  use ProphecyTrait;

  protected TranslationManager $translator;
  protected FileSystemInterface $fileSystem;
  protected ImageElementFactory $factory;

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

    $mockFileSystem = $this->prophesize(FileSystemInterface::class);
    $mockFileSystem->prepareDirectory('public://', 0)->willReturn(TRUE);
    $mockFileSystem->prepareDirectory('invalid_location', 0)->willReturn(FALSE);
    $this->fileSystem = $mockFileSystem->reveal();

    $this->translator = $container->get('string_translation');
    $this->factory = new ImageElementFactory(
      $this->translator,
      new TestEntityRepository($entityTypeManager),
      $this->fileSystem
    );

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
          '@size' => '200 KB',
        ],
      ),
      '#upload_validators' => [
        'file_validate_is_image' => [],
        'file_validate_extensions' => ['gif png jpg jpeg webp'],
        'file_validate_size' => [204800],
      ],
      '#title' => 'Test Field',
      '#theme' => 'image_widget',
      '#trim_file_link' => 20,
      '#weight' => 0,
    ];
  }

  public function testCreateThrowsExceptionOnFileNotExist(): void {
    $options = new ImageElementOptions('Test Field', fileId: 5);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The file id "5" does not exist in the database!');
    $this->factory->create($options);
  }

  public function testCreateThrowsExceptionOnInvalidUploadLocation(): void {
    $options = new ImageElementOptions('Test Field', uploadLocation: 'invalid_location');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The upload location "invalid_location" does not exist or is not writable!');
    $this->factory->create($options);
  }

  public function testCreateThrowsExceptionOnInvalidImageStyle(): void {
    $options = new ImageElementOptions('Test Field', previewImageStyle: 'invalid_style');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The image style "invalid_style" does not exist in the database!');
    $this->factory->create($options);
  }

  public function testCreateWithBasicConfig(): void {
    $options = new ImageElementOptions('Test Field');

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();

    $this->assertEquals($expectedResult, $result);
  }

  /**
   * @dataProvider provideDescriptions
   */
  public function testCreateWithDescriptionOverride(bool $useTranslation): void {
    if ($useTranslation) {
      $description = $this->translator->translate('Test description');
    }
    else {
      $description = 'Test description';
    }

    $options = new ImageElementOptions('Test Field', descriptionOverride: $description);

    $result = $this->factory->create($options);
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

  public function testCreateWithFileId(): void {
    $options = new ImageElementOptions('Test Field', fileId: 1);

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#default_value'] = [1];

    $this->assertEquals($expectedResult, $result);
  }

  public function testCreateWithPreviewImageStyle(): void {
    $options = new ImageElementOptions('Test Field', previewImageStyle: 'valid_style');

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#preview_image_style'] = 'valid_style';

    $this->assertEquals($expectedResult, $result);
  }

  public function testCreateWithOptimalDimensions(): void {
    $options = new ImageElementOptions('Test Field', optimalDimensions: UploadDimensions::fromArray(['width' => 35, 'height' => 78]));

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#description'] = $this->translator->translate(
      'Allowed extensions: @extensions<br />Optimal dimensions: @widthpx x @heightpx<br />Max size: @size',
      [
        '@extensions' => 'gif png jpg jpeg webp',
        '@size' => '200 KB',
        '@width' => 35,
        '@height' => 78,
      ],
    );

    $this->assertEquals($expectedResult, $result);
  }

  public function testCreateWithUploadLocation(): void {
    $options = new ImageElementOptions('Test Field', uploadLocation: 'public://');

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#upload_location'] = 'public://';

    $this->assertEquals($expectedResult, $result);
  }

  public function testCreateWithAllowedExtensions(): void {
    $options = new ImageElementOptions('Test Field', allowedExtensions: ['gif']);

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#description'] = $this->translator->translate(
      'Allowed extensions: @extensions<br />Max size: @size',
      [
        '@extensions' => 'gif',
        '@size' => '200 KB',
      ],
    );
    $expectedResult['#upload_validators']['file_validate_extensions'] = ['gif'];

    $this->assertEquals($expectedResult, $result);
  }

  public function testCreateWithMaxSize(): void {
    $options = new ImageElementOptions('Test Field', maxSize: '50 KB');

    $result = $this->factory->create($options);
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

  public function testCreateWithWeight(): void {
    $options = new ImageElementOptions('Test Field', weight: -5);

    $result = $this->factory->create($options);
    $expectedResult = $this->getDefaultExpectedResult();
    $expectedResult['#weight'] = -5;

    $this->assertEquals($expectedResult, $result);
  }

}
