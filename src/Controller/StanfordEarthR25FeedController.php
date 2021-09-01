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
 * Provide R25 event feed by location to fullcalendar js.
 */
class StanfordEarthR25FeedController extends ControllerBase {

  private function _stanford_r25_feed_get_value(&$results, $name, $key) {
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
   * @return markup
   */
  public function feed(EntityInterface $r25_location, Request $request) {

    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');

    // format the request to the 25Live API from either POST or GET arrays
    $room_id = $r25_location->get('id');
    $space_id = $r25_location->get('space_id');
    if (empty($room_id) || empty($space_id)) {
      throw new Exception\NotFoundHttpException();
    }
    $params = [];
    if ($request->getMethod() == 'GET') {
      $params = $request->query->all();
    }
    else if ($request->getMethod() == 'POST') {
      $params = $request->request->all();
    }
    $start = '';
    $end = '';
    if (!empty($params['start'])) {
      $start = str_replace('-', '', $params['start']);
      if (strpos($start, "T") !== FALSE) {
        $start = substr($start,0, strpos($start, "T"));
      }
    }
    if (!empty($params['end'])) {
      $end = str_replace('-', '', $params['end']);
      if (strpos($end, "T") !== FALSE) {
        $end = substr($end, 0, strpos($end, "T"));
      }
    }

    // depending on the logged in user requesting this information, we want to include
    // links to contact the event scheduler, or to confirm or cancel the event
    $approver_list = array();
    $secgroup = $r25_location->get('approver_secgroup_id');
    if (!empty($secgroup)) {
      $approver_list = StanfordEarthR25Util::_stanford_r25_security_group_emails($secgroup);
    }

    $approver = FALSE;
    // if the user is Drupal user 1 or can administer rooms, let them approve
    // and cancel events
    $user = \Drupal::currentUser();
    if ($user->hasPermission('administer stanford r25') ||
      ($user->isAuthenticated() &&
        in_array($user->getEmail(), $approver_list))) {
      $approver = TRUE;
    }

    // build the 25Live API request with the space id for the requested room and
    // for the start and end dates requested by fullcalendar
    $args = 'space_id=' . $space_id . '&scope=extended&start_dt=' . $start . '&end_dt=' . $end;
    $items = [];
    // make the API call
    $r25_result = $r25_service->r25_api_call('feed', $args);
    if ($r25_result['status']['status'] === TRUE &&
      !empty($r25_result['output']['index']['R25:RESERVATION_ID'])) {

      $results = $r25_result['output'];
      // for each result, store the data in the return array
      foreach ($results['index']['R25:RESERVATION_ID'] as $key => $value) {
        $id = $results['vals'][$value]['value'];
        $event_id = $this->_stanford_r25_feed_get_value($results, 'R25:EVENT_ID', $key);
        $title = $this->_stanford_r25_feed_get_value($results, 'R25:EVENT_NAME', $key);
        $start = $this->_stanford_r25_feed_get_value($results, 'R25:RESERVATION_START_DT', $key);
        $end = $this->_stanford_r25_feed_get_value($results, 'R25:RESERVATION_END_DT', $key);
        $headcount = $this->_stanford_r25_feed_get_value($results, 'R25:EXPECTED_COUNT', $key);
        $state = $this->_stanford_r25_feed_get_value($results, 'R25:STATE', $key);
        $state_text = $this->_stanford_r25_feed_get_value($results, 'R25:STATE_NAME', $key);
        $items[] = array(
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
        );
      }

      // for logged in users, we want to display event status, headcount, and who did the booking
      if ($user->isAuthenticated()) {
        // find out if event was *not* scheduled by QuickBook account and then get the scheduler
        $config = \Drupal::configFactory()->getEditable('stanford_earth_r25.credentialsettings');
        $quickbook_id = intval($config->get('stanford_r25_credential_contact_id'));
        foreach ($results['index']['R25:SCHEDULER_ID'] as $key => $value) {
          if (intval($results['vals'][$value]['value']) != $quickbook_id) {
            $text = '';
            if (isset($results['vals'][$results['index']['R25:SCHEDULER_ID'][$key]]['value'])) {
              $text = 'Reservation scheduled in 25Live by ' .
                $results['vals'][$results['index']['R25:SCHEDULER_NAME'][$key]]['value'] . '.';
            }
            if (isset($results['vals'][$results['index']['R25:SCHEDULER_EMAIL'][$key]]['value'])) {
              $email = $results['vals'][$results['index']['R25:SCHEDULER_EMAIL'][$key]]['value'];
              if (empty($text)) {
                $text = 'Reservation scheduled in 25Live by ' . $email . '.';
              }
              $text .= '&nbsp;<a href="mailto:' . $email . '">Click to contact scheduler by email</a>.';
            }
            if (empty($text)) {
              $text = 'Scheduler is unknown.';
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

        // for those items that were scheduled by quickbook, the event description contains the scheduler.
        // also, certain rooms may want to show the description as the FullCalendar event title
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
                // display event description as event title if room is so marked
                if (!empty($r25_location->get('description_as_title')) &&
                  intval($r25_location->get('description_as_title')) == 1) {
                  $items[$index]['title'] = Mail\MailFormatHelper::htmlToText($text);
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
            if ($approver) {
              $can_confirm = TRUE;
            }
          }
          $items[$key]['tip'] = 'Status: ' . $item['state_name'] . '<br />' . 'Headcount: ' . $item['headcount'];
          if (!empty($item['description'])) {
            $items[$key]['tip'] .= '<br />' . $item['description'];
          }
          if (!empty($item['scheduled_by'])) {
            $items[$key]['tip'] .= '<br />' . $item['scheduled_by'];
          }
          //drupal_alter('stanford_r25_contact', $items[$key]['tip']);
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
            if (!empty($scheduler_email) && $scheduler_email === $user->getEmail()) {
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
    \Drupal::service('page_cache_kill_switch')->trigger();
    return JsonResponse::create($items);
  }

}
