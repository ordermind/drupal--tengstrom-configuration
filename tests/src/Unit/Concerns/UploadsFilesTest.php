<?php

declare(strict_types=1);

namespace Drupal\Tests\tengstrom_configuration\Concerns;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileStorage;
use Drupal\tengstrom_configuration\Concerns\UploadsFiles;
use Drupal\Tests\tengstrom_configuration\Unit\Fixtures\FileEntity;
use Drupal\Tests\UnitTestCase;
use Ordermind\DrupalTengstromShared\Test\Fixtures\EntityStorage\ContentEntityArrayStorage;
use Ordermind\DrupalTengstromShared\Test\Fixtures\Factories\TestServiceContainerFactory;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;

class UploadsFilesTest extends UnitTestCase {

  use ProphecyTrait;

  protected function createConfig(): Config {
    $mockTypedConfigManager = $this->prophesize(TypedConfigManagerInterface::class);
    $typedConfigManager = $mockTypedConfigManager->reveal();

    return new Config('test_config.settings', new MemoryStorage(), new EventDispatcher(), $typedConfigManager);
  }

  /**
   * @dataProvider provideSaveFieldFieldCases
   */
  public function testSaveFileField(
    bool $expectedOldFileDeleted,
    ?string $oldFileUuid,
    ?string $newFileUuid,
  ): void {
    $containerFactory = new TestServiceContainerFactory();
    $container = $containerFactory->createWithBasicServices();
    \Drupal::setContainer($container);

    $fileType = new EntityType([
      'id' => 'file',
      'class' => FileEntity::class,
      'entity_keys' => [
        'id' => 'id',
        'uuid' => 'uuid',
        'label' => 'label',
      ],
    ]);

    $fileStorage = ContentEntityArrayStorage::createInstance($container, $fileType);

    $mockEntityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $mockEntityTypeManager->getStorage($fileType->id())->willReturn($fileStorage);
    $mockEntityTypeManager->getDefinition($fileType->id())->willReturn($fileType);
    $entityTypeManager = $mockEntityTypeManager->reveal();
    $container->set('entity_type.manager', $entityTypeManager);

    $oldFile = NULL;
    $oldFileId = NULL;
    if ($oldFileUuid) {
      $oldFile = $fileStorage->create(['uuid' => $oldFileUuid]);
      $oldFile->save();
      $oldFileId = $oldFile->id();
    }

    $newFile = NULL;
    $newFileId = NULL;
    if ($newFileUuid) {
      $newFile = $fileStorage->create(['uuid' => $newFileUuid]);
      $newFile->save();
      $newFileId = $newFile->id();
    }

    $testSubject = new class($fileStorage) {

      public function __construct(private EntityStorageInterface $fileStorage) {}

      use UploadsFiles {
        saveFileField as public;
      }

      protected function getFileStorage(): EntityStorageInterface {
        return $this->fileStorage;
      }

    };

    $testSubject->saveFileField($oldFileId, $newFileId);

    // If neither the old file or new file have been created in the context, just check that they are not stored.
    if (!$oldFile && !$newFile) {
      $this->assertSame(0, $fileStorage->count());
    }

    // If the old file has been created in the context, check that it is deleted if it is expected to be, and otherwise not.
    if ($oldFile) {
      if ($expectedOldFileDeleted) {
        $this->assertNull($fileStorage->load($oldFile->id()));
      }
      else {
        $this->assertSame($oldFile, $fileStorage->load($oldFile->id()));
      }
    }

    // If the new file has been created, it should be set to permanent status.
    if ($newFile) {
      $updatedFile = $fileStorage->load($newFile->id());
      $this->assertInstanceOf(FileEntity::class, $updatedFile);
      /** @var \Drupal\Tests\tengstrom_configuration\Unit\Fixtures\FileEntity $updatedFile*/
      $this->assertSame(File::STATUS_PERMANENT, $updatedFile->getStatus());
    }
  }

  public static function provideSaveFieldFieldCases(): array {
    return [
      // Saving form with neither old file nor new file.
      [TRUE, NULL, NULL],
      // Saving form with old file but no new file -> delete old file.
      [TRUE, 'old_file_uuid', NULL],
      // Saving form with new file but no old file -> do not delete old file.
      [FALSE, NULL, 'new_file_uuid'],
      // Saving form with both old file and a new file without change -> do not delete old file.
      [FALSE, 'old_file_uuid', 'old_file_uuid'],
      // Saving form with both old file and a new changed file -> delete old file.
      [TRUE, 'old_file_uuid', 'new_file_uuid'],
    ];
  }

  /**
   * @dataProvider provideGetFileIdFromUuidCases
   */
  public function testGetFileIdFromUuid(?int $expectedFileId, ?string $uuid, array $foundFilesCallbacks): void {
    $mockFileStorage = $this->prophesize(FileStorage::class);
    if ($uuid) {
      $foundFiles = [];
      if (!empty($foundFilesCallbacks)) {
        $foundFiles = array_map(fn ($callback) => $callback($this), $foundFilesCallbacks);
      }
      $mockFileStorage->loadByProperties(['uuid' => $uuid])->willReturn($foundFiles);
    }
    $fileStorage = $mockFileStorage->reveal();

    $testSubject = new class($fileStorage) {

      public function __construct(private FileStorage $fileStorage) {}

      use UploadsFiles {
        getFileIdFromUuid as public;
      }

      protected function getFileStorage(): FileStorage {
        return $this->fileStorage;
      }

    };

    $this->assertSame($expectedFileId, $testSubject->getFileIdFromUuid($uuid));
  }

  public static function provideGetFileIdFromUuidCases(): array {
    $foundFilesCallbacks = array_map(function (int $id) {
      return function (self $testCase) use ($id) {
        $mockFile = $testCase->prophesize(FileInterface::class);
        $mockFile->id()->willReturn($id);

        return $mockFile->reveal();
      };
    }, [1, 2, 3]);

    return [
      [NULL, NULL, []],
      [NULL, 'valid_uuid', []],
      [1, 'valid_uuid', $foundFilesCallbacks],
    ];
  }

  /**
   * @dataProvider provideUpdateModuleConfigCases
   */
  public function testUpdateModuleConfig(?string $expectedUuid, callable $newFileCallback): void {
    $config = $this->createConfig();

    $mockFileStorage = $this->prophesize(FileStorage::class);
    $fileStorage = $mockFileStorage->reveal();

    $testSubject = new class($fileStorage) {

      public function __construct(private FileStorage $fileStorage) {}

      use UploadsFiles {
        updateModuleConfig as public;
      }

      protected function getFileStorage(): FileStorage {
        return $this->fileStorage;
      }

    };

    $this->assertSame(NULL, $config->get('uuid'));

    $testSubject->updateModuleConfig($config, $newFileCallback($this));

    $this->assertSame($expectedUuid, $config->get('uuid'));
  }

  public static function provideUpdateModuleConfigCases(): array {
    $fileCallback = function (self $testCase) {
      $mockFile = $testCase->prophesize(FileInterface::class);
      $mockFile->uuid()->willReturn('valid_uuid');

      return $mockFile->reveal();
    };

    return [
      [NULL, fn (self $testCase) => NULL],
      ['valid_uuid', $fileCallback],
    ];
  }

  /**
   * @dataProvider provideUpdateThemeConfigCases
   */
  public function testUpdateThemeConfig(
    bool $expectedUseDefault,
    string $expectedPath,
    callable $newFileCallback,
  ): void {
    $config = $this->createConfig();

    $mockFileStorage = $this->prophesize(FileStorage::class);
    $fileStorage = $mockFileStorage->reveal();

    $testSubject = new class($fileStorage) {

      public function __construct(private FileStorage $fileStorage) {}

      use UploadsFiles {
        updateThemeConfig as public;
      }

      protected function getFileStorage(): FileStorage {
        return $this->fileStorage;
      }

    };

    $this->assertNull($config->get('test_field.use_default'));
    $this->assertNull($config->get('test_field.path'));

    $testSubject->updateThemeConfig($config, $newFileCallback($this), 'test_field');

    $this->assertSame($expectedUseDefault, $config->get('test_field.use_default'));
    $this->assertSame($expectedPath, $config->get('test_field.path'));
  }

  public static function provideUpdateThemeConfigCases(): array {
    $fileCallback = function (self $testCase) {
      $mockFile = $testCase->prophesize(FileInterface::class);
      $mockFile->getFileUri()->willReturn('valid_uri');

      return $mockFile->reveal();
    };

    return [
      [TRUE, '', fn (self $testCase) => NULL],
      [FALSE, 'valid_uri', $fileCallback],
    ];
  }

  /**
   * @dataProvider provideLoadFileFromIdCases
   */
  public function testLoadFileFromId(callable $expectedResultCallback, ?int $fileId): void {
    $mockFileStorage = $this->prophesize(FileStorage::class);
    if ($fileId) {
      $mockFileStorage->load($fileId)->willReturn($this->prophesize(FileInterface::class)->reveal());
    }
    $fileStorage = $mockFileStorage->reveal();

    $testSubject = new class($fileStorage) {

      public function __construct(private FileStorage $fileStorage) {}

      use UploadsFiles {
        loadFileFromId as public;
      }

      protected function getFileStorage(): FileStorage {
        return $this->fileStorage;
      }

    };

    $result = $testSubject->loadFileFromId($fileId);
    $this->assertEquals($expectedResultCallback($this), $result);
  }

  public static function provideLoadFileFromIdCases(): array {
    return [
      [fn (self $testCase) => NULL, NULL],
      [fn (self $testCase) => $testCase->prophesize(FileInterface::class)->reveal(), 5],
    ];
  }

  /**
   * @dataProvider provideFileIdCases
   */
  public function testGetOldFileId(?int $expectedResult, array $fieldValue): void {
    $fieldName = 'test_field';

    // Prophecy does not support passing functions by reference so in this case we have to
    // use a different mocking framework.
    $completeForm = [
      $fieldName => [
        '#default_value' => $fieldValue,
      ],
    ];
    $mockFormState = $this->createStub(FormStateInterface::class);
    $mockFormState->method('getCompleteForm')->willReturn($completeForm);
    /** @var \Drupal\Core\Form\FormStateInterface $mockFormState */

    $mockFileStorage = $this->prophesize(FileStorage::class);
    $fileStorage = $mockFileStorage->reveal();

    $testSubject = new class($fileStorage) {

      public function __construct(private FileStorage $fileStorage) {}

      use UploadsFiles {
        getOldFileId as public;
      }

      protected function getFileStorage(): FileStorage {
        return $this->fileStorage;
      }

    };

    $result = $testSubject->getOldFileId($mockFormState, $fieldName);
    $this->assertSame($expectedResult, $result);
  }

  /**
   * @dataProvider provideFileIdCases
   */
  public function testGetNewFileId(?int $expectedResult, array $fieldValue): void {
    $fieldName = 'test_field';

    // Prophecy does not support passing functions by reference so in this case we have to
    // use a different mocking framework.
    $mockFormState = $this->createStub(FormStateInterface::class);
    /** @var \PHPUnit\Framework\MockObject\MockObject $mockFormState */
    $matcher = $this->exactly(1);
    $mockFormState->expects($matcher)->method('getValue')->willReturnCallback(function (...$parameters) use ($matcher, $fieldName, $fieldValue) {
      if ($matcher->numberOfInvocations() === 1) {
        $this->assertSame($fieldName, $parameters[0]);
      }
      return $fieldValue;
    });

    $mockFileStorage = $this->prophesize(FileStorage::class);
    $fileStorage = $mockFileStorage->reveal();

    $testSubject = new class($fileStorage) {

      public function __construct(private FileStorage $fileStorage) {}

      use UploadsFiles {
        getNewFileId as public;
      }

      protected function getFileStorage(): FileStorage {
        return $this->fileStorage;
      }

    };

    /** @var \Drupal\Core\Form\FormStateInterface $mockFormState */
    $result = $testSubject->getNewFileId($mockFormState, $fieldName);
    $this->assertSame($expectedResult, $result);
  }

  public static function provideFileIdCases(): array {
    return [
      [NULL, []],
      [5, [5]],
      [5, [5, 10]],
    ];
  }

}
