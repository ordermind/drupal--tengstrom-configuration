<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\ElementBuilders;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tengstrom_configuration\ValueObjects\FileSize;
use Drupal\tengstrom_configuration\ValueObjects\UploadDimensions;

class ImageElementDefaultDescriptionFactory {
  protected TranslationInterface $translator;

  public function __construct(TranslationInterface $translator) {
    $this->translator = $translator;
  }

  /**
   * @param string[] $allowedExtensions
   */
  public function create(
    array $allowedExtensions,
    FileSize $maxSize,
    ?UploadDimensions $optimalDimensions = NULL
  ): TranslatableMarkup {
    $translationArguments = [
      '@extensions' => implode(' ', $allowedExtensions),
      '@size' => $maxSize->getFormattedValue(),
    ];

    $translationString = 'Allowed extensions: @extensions';
    if ($optimalDimensions) {
      $translationString .= '<br />Optimal dimensions: @widthpx x @heightpx';
      $translationArguments['@width'] = $optimalDimensions->getWidth();
      $translationArguments['@height'] = $optimalDimensions->getHeight();
    }
    $translationString .= '<br />Max size: @size';

    return $this->translator->translate($translationString, $translationArguments);

  }

}