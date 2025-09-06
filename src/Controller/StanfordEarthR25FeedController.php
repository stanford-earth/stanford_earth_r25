<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\stanford_earth_r25\Entity\StanfordEarthR25LocationInterface;

/**
 * Provide R25 event feed by location to fullcalendar js.
 */
class StanfordEarthR25FeedController extends ControllerBase {

  /**
   * Page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   *   The kill switch service.
   */
  protected $killSwitch;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   *   The config factory service.
   */
  protected $configFactory;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected $user;

  /**
   * Stanford R25 API Service.
   *
   * @var \Drupal\stanford_earth_r25\Service\StanfordEarthR25Service
   */
  protected $r25Service;

  /**
   * Drupal ModuleHandlerInterface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   *   ModulehandlerInterface to call hooks.
   */
  protected $moduleHandler;

  /**
   * StanfordEarthR25FeedController constructor.
   */
  public function __construct(
    KillSwitch $killSwitch,
    ConfigFactory $configFactory,
    AccountInterface $user,
    StanfordEarthR25Service $r25Service,
    ModuleHandlerInterface $moduleHandler,
  ) {
    $this->killSwitch = $killSwitch;
    $this->configFactory = $configFactory;
    $this->user = $user;
    $this->r25Service = $r25Service;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('stanford_earth_r25.r25_call'),
      $container->get('module_handler')
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
   * @param \Drupal\stanford_earth_r25\Entity\StanfordEarthR25LocationInterface $r25_location
   *   Location for which to get a feed.
   * @param string $type
   *   The type of feed to retrieve.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JsonRespone object with calendar feed data.
   */
  public function feed(StanfordEarthR25LocationInterface $r25_location, string $type, Request $request) {

    // Format the request to the 25Live API from either POST or GET arrays.
    $room_id = $r25_location->get('id');
    $space_id = $r25_location->get('space_id');
    if (empty($room_id) || empty($space_id)) {
      throw new NotFoundHttpException();
    }

    // Build an abbreviation list for multi-room calendars.
    $abbreviations = [];
    if (strpos($space_id, '+') !== FALSE) {
      $locations = explode('+', $space_id);
      $abbs = explode('+', $r25_location->get('abbreviations'));
      foreach ($locations as $key => $location) {
        if (!empty($abbs[$key])) {
          $abbreviations[$location] = $abbs[$key];
        }
        else {
          $abbreviations[$location] = '';
        }
      }
    }

    // Double-check if the user can view the calendar based on overrides.
    if (!StanfordEarthR25Util::stanfordR25CanViewRoom($r25_location,
                                                      $this->user,
                                                      $this->moduleHandler)) {
      $this->killSwitch->trigger();
      return new JsonResponse([]);
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

    // If this is an availability list, get possible timeslots.
    $availability = FALSE;
    $timeslots = [];
    if (intval($r25_location->get('caltype')) === 3) {
      $availability = TRUE;
      $timeslots = StanfordEarthR25Util::stanfordR25PossibleTimeslots(
        $r25_location, $start, $end);
      // No timeslots? Send an empty list.
      if (empty($timeslots)) {
        $this->killSwitch->trigger();
        return new Response('', 404);
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
    $r25_result = $this->r25Service->stanfordR25ApiCall($type, $args);
    if ($availability) {
      // we're building an availability list calendar of free events.
      $eventName = $r25_location->get('override_event_name');
      if (empty($eventName)) {
        $eventName = 'Available';
      }
      if ($r25_result['status']['status'] === TRUE) {
        $results = $r25_result['output'];
        if (!empty($results['index']['R25:RESERVATION_ID'])) {
          foreach ($results['index']['R25:RESERVATION_ID'] as $key => $value) {
            $start1 = new DrupalDateTime($this->stanfordR25FeedGetValue($results,
              'R25:RESERVATION_START_DT', $key));
            $end1 = new DrupalDateTime($this->stanfordR25FeedGetValue($results,
              'R25:RESERVATION_END_DT', $key));
            foreach ($timeslots as $slotkey => $slot) {
              if ($slot['free'] && $start1->format('Y-m-d') ===
                $slot['start']->format('Y-m-d')) {
                $free = FALSE;
                if ($end1 <= $slot['start'] || $start1 >= $slot['end']) {
                  $free = TRUE;
                }
                $timeslots[$slotkey]['free'] = $free;
              }
            }
          }
        }
        foreach ($timeslots as $slot) {
          if ($slot['free']) {
            $items[] = [
              'title' => $eventName,
              'start' => $slot['start']->format("Y-m-d\\TH:i:sP"),
              'end' => $slot['end']->format("Y-m-d\\TH:i:sP"),
              'price' => $slot['price'],
            ];
          }
        }
      }
    }
    else {
      // we're building a regular calendar of booked events.
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
          $space_idx = $this->stanfordR25FeedGetValue($results, 'R25:SPACE_ID', $key);
          $related_space = $this->stanfordR25FeedGetValue($results, 'R25:RELATED_SPACE_ID', $key);
          $scheduler_id = $this->stanfordR25FeedGetValue($results, 'R25:SCHEDULER_ID', $key);
          $scheduler_namex = $this->stanfordR25FeedGetValue($results, 'R25:SCHEDULER_NAME', $key);
          $scheduler_email = $this->stanfordR25FeedGetValue($results, 'R25:SCHEDULER_EMAIL', $key);
          $descriptionText = $this->stanfordR25FeedGetValue($results, 'R25:EVENT_DESCRIPTION', $key);
          $event_type = $this->stanfordR25FeedGetValue($results, 'R25:EVENT_TYPE_NAME', $key);
          $space_name = $this->stanfordR25FeedGetValue($results, 'R25:SPACE_NAME', $key);
          $abbreviations_used = FALSE;
          if (!empty($abbreviations[$space_idx])) {
            $abbreviations_used = TRUE;
            $title = $abbreviations[$space_idx] . ": " . $title;
          }
          if (empty($related_space)) {
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
              'space_id' => $space_idx,
              'scheduler_id' => $scheduler_id,
              'scheduler_name' => $scheduler_namex,
              'scheduler_email' => $scheduler_email,
              'description_text' => $descriptionText,
              'space_name' => $space_name,
              'type' => $event_type,
              'abbreviations' => $abbreviations_used,
            ];
          }
        }

        // Set up arrays of background colors for multi-room calendars.
        $colors = [];
        $tentativeColors = [];
        $legend_labels = $r25_location->get('legend_labels');
        if (!empty($legend_labels)) {
          $spaces = explode('+', $r25_location->get('space_id'));
          $labels = explode('+', $legend_labels);
          foreach ($spaces as $key => $space) {
            if (!empty($labels[$key])) {
              $label_ex = explode('|', $labels[$key]);
              if (!empty($label_ex[1])) {
                $colors[$space] = $label_ex[1];
              }
              if (!empty($label_ex[2])) {
                $tentativeColors[$space] = $label_ex[2];
              }
            }
          }
        }

        // For logged in users, we want to display event status, headcount,
        // and who did the booking.
        if ($this->user->isAuthenticated()) {
          // Find out if event was *not* scheduled by QuickBook account and then
          // get the schedule.
          $config = $this->configFactory->getEditable('stanford_earth_r25.adminsettings');
          $quickbook_id = intval($config->get('stanford_r25_credential_contact_id'));
          foreach ($items as $key => $item) {
            if (!empty($item['scheduler_id']) && intval($item['scheduler_id']) !== $quickbook_id) {
              $scheduler_name = $item['scheduler_name'];
              if (!empty($scheduler_name) && str_contains($scheduler_name, ',')) {
                $name_array = explode(',', $scheduler_name);
                $last = '';
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
              $email = $item['scheduler_email'];
              if (empty($scheduler_name)) {
                if (empty($email)) {
                  $scheduler_name = 'Unknown user. Please check 25Live audit trail.';
                }
                else {
                  $scheduler_name = $email;
                }
              }
              $text = 'Reservation scheduled in 25Live by ' . $scheduler_name . '.';
              if (!empty($email) && intval($item['scheduler_id']) !== $quickbook_id) {
                $text .= '&nbsp;<a href="mailto:' . $email . '">Click to contact scheduler by email</a>.';
              }
              $items[$key]['scheduled_by'] = $text;
            }
          }

          // For those items that were scheduled by quickbook, the event
          // description contains the scheduler. Also, certain rooms may want to
          // show the description as the FullCalendar event title.
          foreach ($items as $key => $item) {
            $text = $item['description_text'];
            if (!empty($text)) {
              // Display event description as title if room is so marked.
              if (!empty($r25_location->get('description_as_title')) &&
                intval($r25_location->get('description_as_title')) == 1) {
                $reserver = '';
                $name_pos1 = strpos($text, 'made by ');
                if ($name_pos1 !== FALSE) {
                  $name_pos1 += 8;
                  $name_pos2 = strpos($text, " - ", $name_pos1);
                  if ($name_pos2 !== FALSE) {
                    $reserver = substr($text, $name_pos1, $name_pos2 - $name_pos1);
                  }
                }
                if (!empty($reserver)) {
                  if ($items[$key]['abbreviations']) {
                    $title_parts = explode(':', $items[$key]['title'], 2);
                    if (count($title_parts) > 1) {
                      $reserver = $title_parts[0] . ': ' . $reserver;
                      $items[$key]['title'] = $title_parts[1];
                    }
                  }
                  $items[$key]['title'] = $reserver . ' - ' . $items[$key]['title'];
                }
              }
              $items[$key]['description'] = $text;
            }
          }

          foreach ($items as $key => $item) {
            $can_confirm = FALSE;
            if (!empty($colors[$item['space_id']])) {
              $items[$key]['backgroundColor'] = $colors[$item['space_id']];
            }
            if (intval($item['state']) == 1) {
              if (empty($tentativeColors[$item['space_id']])) {
                $items[$key]['backgroundColor'] = 'goldenrod';
              }
              else {
                $items[$key]['backgroundColor'] = $tentativeColors[$item['space_id']];
              }
              $items[$key]['textColor'] = 'black';
              $items[$key]['title'] .= ' (' . $item['state_name'] . ')';
              if ($approver) {
                $can_confirm = TRUE;
              }
            }
            $toolTipStr = 'Status: ' . $item['state_name'] . '<br />' .
              'Headcount: ' . $item['headcount'] . '<br />' . $item['space_name'];
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
              if (!empty($scheduler_email) &&
                $scheduler_email === $this->user->getEmail()) {
                $can_cancel = TRUE;
              }
            }

            if ($can_confirm) {
              $url = Url::fromUserInput('/r25/modify/confirm/' .
                $room_id . '/' . $items[$key]['event_id'] . '/' .
                $items[$key]['start'])->toString();
              $items[$key]['tip'] .= '<br /><a href="' . $url .
                '">Click to confirm reservation</a>';
            }
            if ($can_cancel) {
              $url = Url::fromUserInput('/r25/modify/cancel/' .
                $room_id . '/' . $items[$key]['event_id'] . '/' .
                $items[$key]['start'])->toString();
              $items[$key]['tip'] .= '<br /><a href="' . $url .
                '">Click to cancel reservation</a>';
            }

            if ($approver) {
              $url = 'https://25live.collegenet.com/pro/stanford#!/home/event/' .
                $items[$key]['event_id'] . '/details';
              $items[$key]['tip'] .= '<br /><a href="' . $url .
                '">Click to manage in 25Live</a>';
            }

            if ($r25_location->get('hide_titles_for_non_managers') && !$can_cancel) {
              $items[$key]['title'] = 'Reserved';
              $items[$key]['description'] = 'Reserved';
              $items[$key]['description_text'] = 'Reserved';
              $items[$key]['tip'] = '';
            }
          }
          if ($type === 'download') {
            if (!empty($results['index']['R25:ATTRIBUTE_NAME']) && is_array($results['index']['R25:ATTRIBUTE_NAME'])) {
              foreach ($results['index']['R25:ATTRIBUTE_NAME'] as $key => $value) {
                $reverse = array_reverse($items, TRUE);
                foreach ($reverse as $idx_key => $idx_val) {
                  if ($value > $reverse[$idx_key]['index']) {
                    $output_key = '';
                    switch ($results['vals'][$value]['value']) {
                      case 'SDSS Booking Contact Info':
                        $output_key = 'contact';
                        break;

                      case 'SDSS PTA':
                        $output_key = 'pta_number';
                        break;

                      case 'SDSS Sponsoring Department':
                        $output_key = 'pta_auth';
                        break;

                      case 'SDSS Food Preferences':
                        $output_key = 'food';
                        break;

                      case 'SDSS Description':
                        $output_key = 'extra_description';
                        break;

                    }
                    if (!empty($output_key)) {
                      $items[$idx_key][$output_key] =
                        $results['vals'][$results['index']['R25:ATTRIBUTE_VALUE'][$key]]['value'];
                    }
                  }
                }
              }
            }
          }
        }
        else {
          foreach ($items as $key => $item) {
            // Hide event titles for anonymous users if location specifies.
            if ($r25_location->get('hide_titles_for_non_managers')) {
              $items[$key]['title'] = 'Reserved';
              $items[$key]['description'] = 'Reserved';
              $items[$key]['description_text'] = 'Reserved';
              $items[$key]['tip'] = '';
            }
            if (!empty($colors[$item['space_id']])) {
              $items[$key]['backgroundColor'] = $colors[$item['space_id']];
            }
          }
        }
      }
    }
    $this->killSwitch->trigger();
    // If timeslot calendar and no availability within 6 months, send an error.
    // This allows fullcalendar to show first available on initial display.
    if (intval($r25_location->get('caltype')) === 3 && empty($items)) {
      $search_start = DrupalDateTime::createFromFormat('Ymd', $start);
      $search_limit = DrupalDateTime::createFromTimestamp(time());
      $search_limit->modify('+6 months');
      if ($search_start <= $search_limit) {
        return new Response('', 404);
      }
    }
    return new JsonResponse($items);
  }

}
