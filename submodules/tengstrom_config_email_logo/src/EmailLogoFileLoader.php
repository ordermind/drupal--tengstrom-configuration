<?php

declare(strict_types=1);

namespace Drupal\tengstrom_config_email_logo;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileStorageInterface;

class EmailLogoFileLoader {
  protected ConfigFactoryInterface $configFactory;
  protected FileStorageInterface $fileStorage;

  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->fileStorage = $entityTypeManager->getStorage('file');
  }

  public function loadLogo(): ?FileInterface {
    $config = $this->configFactory->get('tengstrom_config_email_logo.settings');
    $logoUuid = $config->get('uuid');
    if (!$logoUuid) {
      return NULL;
    }

    $foundFiles = $this->fileStorage->loadByProperties(['uuid' => $logoUuid]);
    if (!$foundFiles) {
      return NULL;
    }

    return reset($foundFiles);
  }

}
