<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Provides a calendar page.
 */
class StanfordEarthR25CalendarController extends ControllerBase {

  /**
   * Page cache kill switch.
   *
   * @var Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   *   The kill switch service.
   */
  protected $killSwitch;

  /**
   * Current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected $user;

  /**
   * Drupal FormBuilder.
   *
   * @var Drupal\Core\Form\FormBuilder
   *   The form builder class.
   */
  protected $formBuilder;

  /**
   * Drupal ModuleHandlerInterface
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   *   ModulehandlerInterface to call hooks.
   */
  protected $moduleHandler;

  /**
   * Drupal Private TempStore (session)
   *
   * @var Drupal\Core\TempStore\PrivateTempStoreFactory
   *   Temporary storage for reservation page.
   */
  protected $tempStore;

  /**
   * StanfordEarthR25FeedController constructor.
   */
  public function __construct(KillSwitch $killSwitch,
                              AccountInterface $user,
                              FormBuilder $formBuilder,
                              ModuleHandlerInterface $moduleHandler,
                              PrivateTempStoreFactory $tempStore) {
    $this->killSwitch = $killSwitch;
    $this->user = $user;
    $this->formBuilder = $formBuilder;
    $this->moduleHandler = $moduleHandler;
    $this->tempStore = $tempStore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('current_user'),
      $container->get('form_builder'),
      $container->get('module_handler'),
      $container->get('tempstore.private')
    );
  }

  /**
   * Returns a calendar page render array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return array
   *   Drupal page markup array.
   */
  public function page(EntityInterface $r25_location, Request $request) {

    $photo_url = NULL;
    if (!empty($r25_location->get('location_info')['photo_id'])) {
      $photo_url = StanfordEarthR25Util::stanfordR25FilePath($r25_location->get('location_info')['photo_id']);
    }

    // Get default view and date params from URL if provided by a permalink.
    $params = $request->query->all();
    foreach ($params as $key => $param) {
      $params[$key] = Html::escape($param);
    }

    // Format the request to the 25Live API from either POST or GET arrays.
    $room_id = $r25_location->get('id');
    $space_id = $r25_location->get('space_id');
    $status = $r25_location->get('displaytype');
    if (empty($room_id) || empty($space_id)) {
      throw new NotFoundHttpException();
    }

    // Build an array of data to pass to javascript.
    $drupalSettings = [];

    // Room status is disabled, view-only, tentative reservable or confirmed
    // reservable.
    $status = intval($status);

    // Double-check if the user can view the calendar based on overrides.
    if (!StanfordEarthR25Util::stanfordR25CanViewRoom($r25_location,
                                                      $this->user,
                                                      $this->moduleHandler)) {
      $status = StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_DISABLED;
    }

    // Make sure we have an enabled room, otherwise display error msg (below).
    if ($status > StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_DISABLED) {
      // Set some JavaScript variables to be used at the browser by
      // stanford_r25_fullcall.js.
      $drupalSettings['stanfordR25Room'] = $r25_location->toArray();
      $drupalSettings['stanfordR25Status'] = $status;
      $drupalSettings['stanfordR25MaxHours'] = $r25_location->get('max_hours');
      $bookable = StanfordEarthR25Util::stanfordR25CanBookRoom($r25_location,
        $this->user, $this->moduleHandler);
      $can_book = ($bookable ? 1 : 0);
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
      }
      else {
        $multi_day = 1;
      }
      $drupalSettings['stanfordR25MultiDay'] = $multi_day;

      // Get the future limit of reservations.
      $calendar_limit = StanfordEarthR25Util::stanfordR25CalendarLimit($r25_location, $this->moduleHandler);
      $drupalSettings['stanfordR25CalendarLimitMonth'] = $calendar_limit['month'];
      $drupalSettings['stanfordR25CalendarLimitYear'] = $calendar_limit['year'];
      $drupalSettings['stanfordR25CalendarLimitDay'] = $calendar_limit['day'];

      $library = [
        'core/drupal.dialog.ajax',
      ];
      if (intval($r25_location->get('caltype')) == 1) {
        $drupalSettings['stanfordR25Spud'] = $r25_location->get('spud_name');
        $library[] = 'stanford_earth_r25/stanford_earth_r25_spud';
      }
      else {
        // If the calendar is FullCalendar, output links to the required
        // javascript files and css.
        $drupalSettings['stanfordR25Timezone'] = date_default_timezone_get();
        if ($this->user->isAuthenticated()) {
          $drupalSettings['stanfordR25Qtip'] = 'qtip';
        }
        $library[] = 'stanford_earth_r25/stanford_earth_r25_calendar';
      }
      $this->tempStore->get('stanfordEarthR25')->set('drupalSettings', $drupalSettings);
      $resForm = $this->formBuilder->getForm('Drupal\stanford_earth_r25\Form\StanfordEarthR25ReservationForm');
      $this->killSwitch->trigger();
      return [
        // Your theme hook name.
        '#theme' => 'stanford_earth_r25-theme-hook',
        // Your variables.
        '#r25_location' => $r25_location,
        '#photo_url' => $photo_url,
        '#form' => $resForm,
        '#canBook' => $bookable,
        '#attached' => [
          'library' => $library,
          'drupalSettings' => [
            'stanfordEarthR25' => $drupalSettings,
          ],
        ],
      ];
    }
    else {
      $this->killSwitch->trigger();
      return [
        '#markup' => $r25_location->label() . ' is not currently available.',
      ];
    }
  }

}
