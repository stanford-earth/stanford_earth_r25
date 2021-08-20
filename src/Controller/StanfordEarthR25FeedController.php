<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provide R25 event feed by location to fullcalendar js.
 */
class StanfordEarthR25FeedController extends ControllerBase {

  /**
   * Return an Ajax dialog command for editing a referenced entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return markup
   */
  public function feed(EntityInterface $r25_location, Request $request) {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('R25 Feed.'),
    ];
  }

}
