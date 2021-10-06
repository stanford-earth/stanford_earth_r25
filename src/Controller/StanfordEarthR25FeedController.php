<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;

/**
 * Provide R25 event feed by location to fullcalendar js.
 */
class StanfordEarthR25FeedController extends ControllerBase {

  /**
   * Page cache kill switch.
   *
   * @var Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   *   The kill switch service.
   */
  protected $killSwitch;

  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactory
   *   The config factory service.
   */
  protected $configFactory;

  /**
   * Current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected $user;

  /**
   * Stanford R25 API Service.
   *
   * @var Drupal\stanford_earth_r25\Service\StanfordEarthR25Service
   */
  protected $r25Service;

  /**
   * StanfordEarthR25FeedController constructor.
   */
  public function __construct(KillSwitch $killSwitch,
                              ConfigFactory $configFactory,
                              AccountInterface $user,
                              StanfordEarthR25Service $r25Service) {
    $this->killSwitch = $killSwitch;
    $this->configFactory = $configFactory;
    $this->user = $user;
    $this->r25Service = $r25Service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('stanford_earth_r25.r25_call')
    );
  }

  /**
   * Return a value from the API XML results.
   *
   * @param array $results
   *   Results array from the API call.
   * @param string $name
   *   Index name of the result we're seeking.
   * @param string $key
   *   Index key of the result we're seeking.
   *
   * @return string
   *   API result value.
   */
  private function stanfordR25FeedGetValue(array &$results, $name, $key) {
    $return_val = '';
    if (isset($results['vals'][$results['index'][$name][$key]]['value'])) {
      $return_val = $results['vals'][$results['index'][$name][$key]]['value'];
    }
    return $return_val;
  }

  /**
   * Return an Ajax dialog command for editing a referenced entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   JsonRespone object with calendar feed data.
   */
  public function feed(EntityInterface $r25_location, Request $request) {

    // Format the request to the 25Live API from either POST or GET arrays.
    $room_id = $r25_location->get('id');
    $space_id = $r25_location->get('space_id');
    if (empty($room_id) || empty($space_id)) {
      throw new NotFoundHttpException();
    }

    // Double-check if the user can view the calendar based on overrides.
    if (!StanfordEarthR25Util::stanfordR25CanViewRoom($r25_location,
                                                      $this->user)) {
      $this->killSwitch->trigger();
      return JsonResponse::create([]);
    }

    $params = [];
    if ($request->getMethod() == 'GET') {
      $params = $request->query->all();
    }
    elseif ($request->getMethod() == 'POST') {
      $params = $request->request->all();
    }
    $start = '';
    $end = '';
    if (!empty($params['start'])) {
      $start = str_replace('-', '', $params['start']);
      if (strpos($start, "T") !== FALSE) {
        $start = substr($start, 0, strpos($start, "T"));
      }
    }
    if (!empty($params['end'])) {
      $end = str_replace('-', '', $params['end']);
      if (strpos($end, "T") !== FALSE) {
        $end = substr($end, 0, strpos($end, "T"));
      }
    }

    // Depending on the logged in user requesting this information, we want to
    // include links to contact the event scheduler, or to confirm or cancel
    // the event.
    $approver_list = [];
    $secgroup = $r25_location->get('approver_secgroup_id');
    if (!empty($secgroup)) {
      $approver_list = StanfordEarthR25Util::stanfordR25SecurityGroupEmails($secgroup);
    }

    $approver = FALSE;
    // If the user is Drupal user 1 or can administer rooms, let them approve
    // and cancel events.
    if ($this->user->hasPermission('administer stanford r25') ||
      ($this->user->isAuthenticated() &&
        in_array($this->user->getEmail(), $approver_list))) {
      $approver = TRUE;
    }

    // Build the 25Live API request with the space id for the requested room and
    // for the start and end dates requested by fullcalendar.
    $args = 'space_id=' . $space_id . '&scope=extended&start_dt=' . $start . '&end_dt=' . $end;
    $items = [];
    // Make the API call.
    $r25_result = $this->r25Service->stanfordR25ApiCall('feed', $args);
    if ($r25_result['status']['status'] === TRUE &&
      !empty($r25_result['output']['index']['R25:RESERVATION_ID'])) {

      $results = $r25_result['output'];
      // For each result, store the data in the return array.
      foreach ($results['index']['R25:RESERVATION_ID'] as $key => $value) {
        $id = $results['vals'][$value]['value'];
        $event_id = $this->stanfordR25FeedGetValue($results, 'R25:EVENT_ID', $key);
        $title = $this->stanfordR25FeedGetValue($results, 'R25:EVENT_NAME', $key);
        $start = $this->stanfordR25FeedGetValue($results, 'R25:RESERVATION_START_DT', $key);
        $end = $this->stanfordR25FeedGetValue($results, 'R25:RESERVATION_END_DT', $key);
        $headcount = $this->stanfordR25FeedGetValue($results, 'R25:EXPECTED_COUNT', $key);
        $state = $this->stanfordR25FeedGetValue($results, 'R25:STATE', $key);
        $state_text = $this->stanfordR25FeedGetValue($results, 'R25:STATE_NAME', $key);
        $items[] = [
          'id' => $id,
          'event_id' => $event_id,
          'index' => $value,
          'title' => $title,
          'start' => $start,
          'end' => $end,
          'headcount' => $headcount,
          'state' => $state,
          'state_name' => $state_text,
          'scheduled_by' => '',
          'tip' => '',
        ];
      }

      // For logged in users, we want to display event status, headcount,
      // and who did the booking.
      if ($this->user->isAuthenticated()) {
        // Find out if event was *not* scheduled by QuickBook account and then
        // get the schedule.
        $config = $this->configFactory->getEditable('stanford_earth_r25.credentialsettings');
        $quickbook_id = intval($config->get('stanford_r25_credential_contact_id'));
        foreach ($results['index']['R25:SCHEDULER_ID'] as $key => $value) {
          if (intval($results['vals'][$value]['value']) != $quickbook_id) {
            $scheduler_name = '';
            if (isset($results['vals'][$results['index']['R25:SCHEDULER_ID'][$key]]['value']) &&
              isset($results['vals'][$results['index']['R25:SCHEDULER_NAME'][$key]]['value'])) {
              $scheduler_name = $results['vals'][$results['index']['R25:SCHEDULER_NAME'][$key]]['value'];
              if (!empty($scheduler_name) && strpos($scheduler_name, ',') !== FALSE) {
                $name_array = explode(',', $scheduler_name);
                foreach ($name_array as $nkey => $name) {
                  $scheduler_name = '';
                  $subname = ucfirst(trim($name));
                  if ($nkey == 0) {
                    $last = $subname;
                  }
                  else {
                    $scheduler_name .= $subname . ' ';
                  }
                }
                $scheduler_name .= $last;
              }
            }
            $email = '';
            if (isset($results['vals'][$results['index']['R25:SCHEDULER_EMAIL'][$key]]['value'])) {
              $email = $results['vals'][$results['index']['R25:SCHEDULER_EMAIL'][$key]]['value'];
            }
            if (empty($scheduler_name)) {
              if (empty($email)) {
                $scheduler_name = 'Unknown user. Please check 25Live audit trail.';
              }
              else {
                $scheduler_name = $email;
              }
            }
            $text = 'Reservation scheduled in 25Live by ' . $scheduler_name . '.';
            if (!empty($email)) {
              $text .= '&nbsp;<a href="mailto:' . $email . '">Click to contact scheduler by email</a>.';
            }
            $index = count($items);
            while ($index) {
              $index -= 1;
              if (intval($value) > intval($items[$index]['index'])) {
                $items[$index]['scheduled_by'] = $text;
                break;
              }
            }
          }
        }

        // For those items that were scheduled by quickbook, the event
        // description contains the scheduler. Also, certain rooms may want to
        // show the description as the FullCalendar event title.
        foreach ($results['index']['R25:EVENT_DESCRIPTION'] as $key => $value) {
          $text = '';
          if (!empty($results['vals'][$results['index']['R25:EVENT_DESCRIPTION'][$key]]['value'])) {
            $text = $results['vals'][$results['index']['R25:EVENT_DESCRIPTION'][$key]]['value'];
          }
          if (!empty($text)) {
            $index = count($items);
            while ($index) {
              $index -= 1;
              if (intval($value) > intval($items[$index]['index'])) {
                // Display event description as title if room is so marked.
                if (!empty($r25_location->get('description_as_title')) &&
                  intval($r25_location->get('description_as_title')) == 1) {
                  $items[$index]['title'] = MailFormatHelper::htmlToText($text);
                }
                $items[$index]['description'] = $text;
                break;
              }
            }
          }
        }
        foreach ($items as $key => $item) {
          $can_confirm = FALSE;
          if (intval($item['state']) == 1) {
            $items[$key]['backgroundColor'] = 'goldenrod';
            $items[$key]['textColor'] = 'black';
            $items[$key]['title'] .= ' (' . $item['state_name'] . ')';
            if ($approver) {
              $can_confirm = TRUE;
            }
          }
          $toolTipStr = 'Status: ' . $item['state_name'] . '<br />' . 'Headcount: ' . $item['headcount'];
          if (!empty($item['description'])) {
            $toolTipStr .= '<br />' . $item['description'];
          }
          if (!empty($item['scheduled_by'])) {
            $toolTipStr .= '<br />' . $item['scheduled_by'];
          }
          $items[$key]['tip'] = $toolTipStr;
          $can_cancel = FALSE;
          if ($approver) {
            $can_cancel = TRUE;
          }
          else {
            $scheduler_email = '';
            $description = $items[$key]['tip'];
            $mailto_pos = strpos($description, '"mailto:');
            if ($mailto_pos !== FALSE) {
              $mailto_endpos = strpos($description, '"', $mailto_pos + 8);
              if ($mailto_endpos !== FALSE) {
                $scheduler_email = substr($description, $mailto_pos + 8, $mailto_endpos - ($mailto_pos + 8));
              }
            }
            if (!empty($scheduler_email) && $scheduler_email === $this->user->getEmail()) {
              $can_cancel = TRUE;
            }
          }

          if ($can_confirm) {
            $url = Url::fromUserInput('/r25/modify/confirm/' . $room_id . '/' . $items[$key]['event_id'] . '/' .
              $items[$key]['start'])->toString();
            $items[$key]['tip'] .= '<br /><a href="' . $url . '">Click to confirm reservation</a>';
          }
          if ($can_cancel) {
            $url = Url::fromUserInput('/r25/modify/cancel/' . $room_id . '/' . $items[$key]['event_id'] . '/' .
              $items[$key]['start'])->toString();
            $items[$key]['tip'] .= '<br /><a href="' . $url . '">Click to cancel reservation</a>';
          }

          if ($approver) {
            $url = 'https://25live.collegenet.com/stanford/#details&obj_type=event&obj_id=' . $items[$key]['event_id'];
            $items[$key]['tip'] .= '<br /><a href="' . $url . '">Click to manage in 25Live</a>';
          }
        }
      }
    }
    $this->killSwitch->trigger();
    return JsonResponse::create($items);
  }

}
