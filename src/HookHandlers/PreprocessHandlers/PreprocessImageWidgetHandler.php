<?php

declare(strict_types=1);

namespace Drupal\tengstrom_configuration\HookHandlers\PreprocessHandlers;

use Drupal\Core\Render\Element;
use Drupal\tengstrom_general\HookHandlers\PreprocessHandlers\PreprocessHandlerInterface;

class PreprocessImageWidgetHandler implements PreprocessHandlerInterface {

  public function preprocess(array &$variables): void {
    $element = $variables['element'];

    $variables['attributes'] = [
      'class' => [
        'image-widget',
        'js-form-managed-file',
        'form-managed-file',
        'clearfix',
      ],
    ];

    if (!empty($element['fids']['#value'])) {
      $file = reset($element['#files']);
      $element['file_' . $file->id()]['filename']['#suffix'] = ' <span class="file-size">(' . format_size($file->getSize()) . ')</span> ';
      $file_variables = [
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
      ];

      // Determine image dimensions.
      if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
        $file_variables['width'] = $element['#value']['width'];
        $file_variables['height'] = $element['#value']['height'];
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $file_variables['width'] = $image->getWidth();
          $file_variables['height'] = $image->getHeight();
        }
        else {
          $file_variables['width'] = $file_variables['height'] = NULL;
        }
      }

      $element['preview'] = [
        '#weight' => -10,
        '#theme' => 'image_style',
        '#width' => $file_variables['width'],
        '#height' => $file_variables['height'],
        '#style_name' => $file_variables['style_name'],
        '#uri' => $file_variables['uri'],
      ];

      // Store the dimensions in the form so the file doesn't have to be
      // accessed again. This is important for remote files.
      $element['width'] = [
        '#type' => 'hidden',
        '#value' => $file_variables['width'],
      ];
      $element['height'] = [
        '#type' => 'hidden',
        '#value' => $file_variables['height'],
      ];
    }

    $variables['data'] = [];
    foreach (Element::children($element) as $child) {
      $variables['data'][$child] = $element[$child];
    }
  }

}
