<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Eluceo\iCal\Component;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\Mail;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

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

    // get default view and date params from URL if provided by a permalink
    $params = $request->query->all();
    foreach ($params as $key => $param) {
      $params[$key] = Html::escape($param);
    }

    // format the request to the 25Live API from either POST or GET arrays
    $room_id = $r25_location->get('id');
    $space_id = $r25_location->get('space_id');
    $status = $r25_location->get('displaytype');
    if (empty($room_id) || empty($space_id)) {
      throw new Exception\NotFoundHttpException();
    }

    // build an array of data to pass to javascript
    $drupalSettings = [];
    $library = ['stanford_earth_r25/stanford_earth_r25_calendar'];

    // room status is disabled, view-only, tentative reservable or confirmed reservable
    $status = intval($status);

    // make sure we have an enabled room, otherwise display an error msg (below)
    if ($status > StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_DISABLED) {
      // set some JavaScript variables to be used at the browser by stanford_r25_fullcall.js
      $drupalSettings['stanfordR25Room'] = $r25_location->toArray();
      $drupalSettings['stanfordR25Status'] = $status;
      $drupalSettings['stanfordR25MaxHours'] = $r25_location->get('max_hours');
      $bookable = StanfordEarthR25Util::_stanford_r25_can_book_room($r25_location);
      $can_book = ($bookable['can_book'] ? 1 : 0);
      $drupalSettings['stanfordR25Access'] = $can_book;
      $drupalSettings['stanfordR25DefaultView'] = $r25_location->get('default_view');
      if (!empty($params['view'])) {
        $drupalSettings['stanfordR25ParamView'] = $params['view'];
      }
      if (!empty($params['date'])) {
        $drupalSettings['stanfordR25ParamDate'] = $params['date'];
      }
      $multi_day = $r25_location->get('multi_day');
      if (empty($multi_day)) {
        $multi_day = 0;
      } else {
        $multi_day = 1;
      }
      $drupalSettings['stanfordR25MultiDay'] = $multi_day;

      // the default calendar limit is for one year in the future, but we have
      // a hook, hook_stanford_r25_fullcalendar_limit_alter(&$calendar_limit)
      // where you can change it
      $calendar_limit = array(
        'room' => $r25_location->toArray(),
        'month' => date('n'),
        'year' => date('Y') + 1
      );
      // TBD
      //drupal_alter('stanford_r25_fullcalendar_limit', $calendar_limit);
      $drupalSettings['stanfordR25CalendarLimitMonth'] = $calendar_limit['month'];
      $drupalSettings['stanfordR25CalendarLimitYear'] = $calendar_limit['year'];

      if (intval($r25_location->get('caltype')) == 1) {
        $drupalSettings['stanfordR25Spud'] = $r25_location->get('spud_name');
        //$library[] = 'stanford_earth_r25/stanford_earth_r25_spud';
      }
      else {
        // if the calendar is FullCalendar, output links to the required javascript files and css
        $drupalSettings['stanfordR25Timezone'] = date_default_timezone_get();
        $user = \Drupal::currentUser();
        if ($user->isAuthenticated()) {
          $drupalSettings['stanfordR25Qtip'] = 'qtip';
        }
        //$library[] = 'stanford_earth_r25/stanford_earth_r25_fullcalendar';
      }
    }
    $resForm = \Drupal::formBuilder()->getForm('Drupal\stanford_earth_r25\Form\StanfordEarthR25ReservationForm');
    // If you want modify the form:
    $resForm['field']['#value'] = 'From my controller';
    \Drupal::service('page_cache_kill_switch')->trigger();
    return [
      // Your theme hook name.
      '#theme' => 'stanford_earth_r25-theme-hook',
      // Your variables.
      '#r25_location' => $r25_location,
      '#photo_url' => $photo_url,
      '#form' => $resForm,
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
          'stanford_earth_r25/stanford_earth_r25_calendar'
        ],
        'drupalSettings' => [
          'stanfordEarthR25' => $drupalSettings
        ]
      ],
    ];
  }

}
