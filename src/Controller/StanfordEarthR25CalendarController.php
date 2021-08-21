<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\Mail;
use Drupal\Core\Url;
/**
 * Provides a calendar page.
 */
class StanfordEarthR25CalendarController extends ControllerBase {

  /**
   * Returns a calendar page render array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return array
   */
  public function page(EntityInterface $r25_location, Request $request) {

    $photo_url = null;
    if (!empty($r25_location->get('location_info')['photo_id'])) {
      $photo_url = StanfordEarthR25Util::_stanford_r25_file_path($r25_location->get('location_info')['photo_id']);
    }
    \Drupal::service('page_cache_kill_switch')->trigger();
    return [
      // Your theme hook name.
      '#theme' => 'stanford_earth_r25-theme-hook',
      // Your variables.
      '#r25_location' => $r25_location,
      '#photo_url' => $photo_url,
      '#attached' => [
        'library' => [
          'stanford_earth_r25/stanford_earth_r25_calendar'
        ],
      ],
    ];

  }

}
