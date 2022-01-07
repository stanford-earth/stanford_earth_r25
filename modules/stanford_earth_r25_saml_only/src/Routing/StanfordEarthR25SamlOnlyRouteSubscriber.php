<?php

namespace Drupal\stanford_earth_r25_saml_only\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class StanfordEarthR25SamlOnlyRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $route) {
      if (strpos($route->getPath(), '/r25/reservation') === 0) {
        $route->setRequirements([]);
        $route->setRequirement('_stanford_earth_r25_saml_only_booking_access_checker', 'TRUE');
        print $route->getPath() . chr(13).chr(10);
        print_r($route);
        print chr(13).chr(10);
        $xyz = 1;
      }
    }
    // Change the route associated with the user profile page (/user, /user/{uid}).
    // if ($route = $collection->get('user.page')) {
    //  $route->setDefault('_controller', '\Drupal\mymodule\Controller\UserController::userPage');
    // }
  }

}
