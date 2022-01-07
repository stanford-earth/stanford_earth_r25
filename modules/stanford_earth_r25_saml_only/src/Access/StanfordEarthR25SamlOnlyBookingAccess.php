<?php

namespace Drupal\stanford_earth_r25_saml_only\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

/**
 * Checks access for displaying a reservation page.
 */
class StanfordEarthR25SamlOnlyBookingAccess implements AccessInterface {

  /**
   * Drupal module handler service
   */
  private $moduleHandler;

  /**
   * Drupal entity type manager
   */
  private $entityTypeManager;

  /**
   * StanfordEarthR25SamlOnlyBookingAccess constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandler
   *   Drupal module handler service
   * @param \Drupal\Core\Entity\EntityTypeManager
   *   Drupal entityTypeManager
   */
  public function __construct(
    ModuleHandler $moduleHandler,
    EntityTypeManager $entityTypeManager) {
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   Retrieve the room id from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatch $route_match) {
    $result = AccessResult::forbidden();
    $room_id = $route_match->getParameter('location_id');
    if (!empty($room_id)) {
      $room_entity = $this->entityTypeManager
        ->getStorage('stanford_earth_r25_location')
        ->load($room_id);
      if (!empty($room_entity)) {
        $bookable = StanfordEarthR25Util::stanfordR25CanBookRoom($room_entity,
          $account, $this->moduleHandler);
        if ($bookable) {
          $result = AccessResult::allowed();
        }
      }
    }
    return $result;
  }
}
