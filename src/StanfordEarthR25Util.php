<?php

namespace Drupal\stanford_earth_r25;

use Drupal\Core\Entity\EntityInterface;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Encapsulates information and utility methods.
 */
class StanfordEarthR25Util {

  // define room status codes as constants
  const STANFORD_R25_ROOM_STATUS_DISABLED = 0;
  const STANFORD_R25_ROOM_STATUS_READONLY = 1;
  const STANFORD_R25_ROOM_STATUS_TENTATIVE = 2;
  const STANFORD_R25_ROOM_STATUS_CONFIRMED = 3;

  // define authentication methods as constants
  const STANFORD_R25_AUTH_DRUPAL_ACCT = 1;
  const STANFORD_R25_AUTH_EXTERN_ACCT = 2;
  const STANFORD_R25_AUTH_EITHER_ACCT = 3;

  /**
   * Parse a blackout date text area into an array of start and end dates.
   *
   * @param string $inStr
   *   Input string from text area.
   */
  public static function parse_blackout_dates($inStr = '') {
    $blackouts = [];
    $blackout_text = trim($inStr);
    if (!empty($blackout_text)) {
      $tmp1 = explode("\n", $blackout_text);
      foreach ($tmp1 as $tmp2) {
        $blackout = trim($tmp2);
        if (preg_match('(\d{4}-\d{2}-\d{2} - \d{4}-\d{2}-\d{2})', $blackout) === 1) {
          $tmp3 = explode(" - ", $blackout);
          $blackouts[] = ['start' => $tmp3[0], 'end' => $tmp3[1]];
        }
      }
    }
    return $blackouts;
  }

  // retrieve the 25Live security group id given the security group name
  public static function _stanford_r25_secgroup_id($secgroup) {
    $groupid = 0;
    if (!empty($secgroup)) {
      $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
      $r25_result = $r25_service->r25_api_call('secgroup', $secgroup);
      if ($r25_result['status']['status'] === TRUE) {
        $results = $r25_result['output'];
        if (!empty($results['vals'][$results['index']['R25:SECURITY_GROUP_ID'][0]]['value'])) {
          $groupid = intval($results['vals'][$results['index']['R25:SECURITY_GROUP_ID'][0]]['value']);
        }
      }
    }
    return $groupid;
  }

  // this function returns an array of email addresses for a 25Live security group
  // whose id is stored with a room configuration. The array will be stored in the
  // Drupal cache table unless forced to refresh with the $reset paramater. Saving
  // the room configuration in its admin page will reset this cached data.
  public static function _stanford_r25_security_group_emails($secgroup_id = NULL, $reset = FALSE) {
    if (empty($secgroup_id)) {
      return [];
    }

    // if the information is in the cache and we're not resetting it
    // then return it from the cache
    $cid = 'stanford_r25:approvers:' . $secgroup_id;
    if (!$reset) {
      if ($cache = \Drupal::cache()->get($cid)) {
        $list = $cache->data;
        return $list;
      }
    }
    // otherwise, request the contact ids and email addresses for the security
    // group members from the 25Live API and store them in the cache.
    $list = [];
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_result = $r25_service->r25_api_call('r25users', $secgroup_id);
    if ($r25_result['status']['status'] === TRUE) {
      $sec_result = $r25_result['output'];
      if (!empty($sec_result['index']['R25:CONTACT_ID']) &&
        is_array($sec_result['index']['R25:CONTACT_ID'])) {
        foreach ($sec_result['index']['R25:CONTACT_ID'] as $key => $value) {
          if (!empty($sec_result['vals'][$value]['value'])) {
            $email = '';
            if (!empty($sec_result['index']['R25:PRIMARY_EMAIL'][$key]) &&
              !empty($sec_result['vals'][$sec_result['index']['R25:PRIMARY_EMAIL'][$key]]['value'])
            ) {
              $email = $sec_result['vals'][$sec_result['index']['R25:PRIMARY_EMAIL'][$key]]['value'];
            }
            $list[$sec_result['vals'][$value]['value']] = $email;
          }
        }
      }
      \Drupal::cache()->set($cid, $list);
    }
    return $list;
  }

  // builds a URI for room photos pulled from 25Live using directory set in config pages
  public static function _stanford_r25_file_path($photo_id) {
    $config = \Drupal::configFactory()->getEditable('stanford_earth_r25.credentialsettings');
    $image_directory = $config->get('stanford_r25_room_image_directory');
    if (empty($image_directory)) {
      $image_directory = '';
    }
    return 'public://' . $image_directory . '/R25_' . $photo_id . '.jpg';
  }


  // query the 25Live database for a bunch of information about the room
  // and return in an array. Also save room image.
  public static function _stanford_r25_get_room_info($space_id = NULL) {
    $room_info = [];
    $default_layout = 0;
    if (!empty($space_id)) {
      // get the room info using the api
      $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
      $r25_result = $r25_service->r25_api_call('roominfo', $space_id);
      if ($r25_result['status']['status'] === TRUE) {
        $results = $r25_result['output'];
        // identify the default room layout, if one is defined, otherwise use the first one
        if (!empty($results['index']['R25:DEFAULT_LAYOUT']) && is_array($results['index']['R25:DEFAULT_LAYOUT'])) {
          foreach ($results['index']['R25:DEFAULT_LAYOUT'] as $dlkey => $dlval) {
            if ($results['vals'][$dlval]['value'] == 'T') {
              $default_layout = $dlkey;
              break;
            }
          }
        }
        // get the room capacity for the layout
        $room_info['capacity'] = NULL;
        if (!empty($results['index']['R25:LAYOUT_CAPACITY'][$default_layout])) {
          $room_info['capacity'] = $results['vals'][$results['index']['R25:LAYOUT_CAPACITY'][$default_layout]]['value'];
        }
        // get any comments and instructions about the room
        $room_info['comments'] = NULL;
        if (!empty($results['index']['R25:COMMENTS'][0])) {
          $room_info['comments'] = $results['vals'][$results['index']['R25:COMMENTS'][0]]['value'];
        }
        $room_info['layout_name'] = NULL;
        if (!empty($results['index']['R25:LAYOUT_NAME'][$default_layout])) {
          $room_info['layout_name'] = $results['vals'][$results['index']['R25:LAYOUT_NAME'][$default_layout]]['value'];
        }
        $room_info['layout_instruction'] = NULL;
        if (!empty($results['index']['R25:LAYOUT_INSTRUCTION'][$default_layout]) &&
          !empty($results['vals'][$results['index']['R25:LAYOUT_INSTRUCTION'][$default_layout]]['value'])) {
          $room_info['layout_instruction'] = $results['vals'][$results['index']['R25:LAYOUT_INSTRUCTION'][$default_layout]]['value'];
        }
        // build a list of features for the room layout
        $layout_features = '';
        $first_feature = TRUE;
        if (!empty($results['index']['R25:FEATURE_NAME'][0])) {
          foreach ($results['index']['R25:FEATURE_NAME'] as $index) {
            if (!$first_feature) {
              $layout_features .= ', ';
            }
            $layout_features .= $results['vals'][$index]['value'];
            $first_feature = FALSE;
          }
        }
        $room_info['layout_features'] = NULL;
        if (!empty($layout_features)) {
          $room_info['layout_features'] = $layout_features;
        }
        // build a list of categories for the room
        $layout_categories = '';
        $first_category = TRUE;
        if (!empty($results['index']['R25:CATEGORY_NAME'][0])) {
          foreach ($results['index']['R25:CATEGORY_NAME'] as $index) {
            if (!$first_category) {
              $layout_categories .= ', ';
            }
            $layout_categories .= $results['vals'][$index]['value'];
            $first_category = FALSE;
          }
        }
        $room_info['layout_categories'] = NULL;
        if (!empty($layout_categories)) {
          $room_info['layout_categories']= $layout_categories;
        }

        // get a room photo if available and store in Drupal files
        $photo_id = NULL;
        if (!empty($results['index']['R25:LAYOUT_PHOTO_ID'][$default_layout])
        ) {
          $photo_id = $results['vals'][$results['index']['R25:LAYOUT_PHOTO_ID'][$default_layout]]['value'];
          $photo_status = $r25_service->r25_api_call('roomphoto', $photo_id);
          if ($photo_status['status']['status'] === TRUE) {
            $photo = $photo_status['output'];
            $destination = SELF::_stanford_r25_file_path($photo_id);
            if (!file_save_data($photo, $destination, FILE_EXISTS_REPLACE)) {
              \Drupal::messenger()->addMessage('Unable to save image for R25 Location ' . $space_id,
                MessengerInterface::TYPE_ERROR);
              $photo_id = NULL;
            }
          }
          else {
            $photo_id = NULL;
          }
        }
        $room_info['photo_id'] = $photo_id;
      }
    }
    return $room_info;
  }

// function that checks if the current user can book a room, based on
// room machine_id and how the room is authenticated

public static function _stanford_r25_can_book_room(EntityInterface $r25_location = NULL) {

    // default return array; user is an internal (Drupal) user
    // who can not book the room
    $can_book = [
      'can_book' => FALSE,
      'auth' => 'internal',
      'external_module' => '',
      'external_acct' => FALSE
    ];
    $room_id = null;
    if (!empty($r25_location)) {
      $room_id = $r25_location->id();
    }
    if (!empty($room_id)) {  // only check if we have a room id, obviously
      $authentication_type = $r25_location->get('authentication_type');
      if (!empty($authentication_type)) {   // only continue if room has an auth type
        // if the room uses internal Drupal accounts, simply check if the current user has the permission
        // if the user is Drupal user 1 or can administer rooms, let them approve
        // and cancel events
        $user = \Drupal::currentUser();
        if (($authentication_type == SELF::STANFORD_R25_AUTH_DRUPAL_ACCT ||
            $authentication_type == SELF::STANFORD_R25_AUTH_EITHER_ACCT) &&
          $user->hasPermission('book r25 rooms'))
        {
          $can_book['can_book'] = TRUE;
        }
        /* TBD
        else {
          // if the user can't book by Drupal permission, and the room supports
          // external accounts, then check the user that way
          if ($rooms[$room_id]['authentication'] == STANFORD_R25_AUTH_EXTERN_ACCT ||
            $rooms[$room_id]['authentication'] == STANFORD_R25_AUTH_EITHER_ACCT
          ) {
            $can_book['auth'] = 'external';
            // see if any module implements hook_stanford_r25_external_user
            $externs = module_implements('stanford_r25_external_user');
            if (!empty($externs) && is_array($externs)) {
              // if so, just use the first one returned
              $can_book['external_module'] = $externs[0];
              // call the stanford_r25_external_user hook for the module found
              // it will return an array of user contact info if okay, or false if not
              $external_acct = module_invoke($externs[0], 'stanford_r25_external_user');
              if (!empty($external_acct)) {
                // we got back a non-empty array, so assume an authenticated user who can book the room
                $can_book['can_book'] = TRUE;
                $can_book['external_acct'] = $external_acct;
              }
            }
          }
        }
        */
      }
    }
    return $can_book; // return the array defined above

  }

  // function to check if a given date is blacked out based on blackout dates
  // and current date.
  // $date is given as a UNIX timestamp
  public static function _stanford_r25_date_blacked_out($date) {

    // if an empty date is given, return false
    if (empty($date)) {
      return FALSE;
    }

    // if I'm currently in a blackout period, the requested date doesn't matter - we consider it blacked out.
    // if I'm *not* currently in a blackout period, then the requested date has to be before the next blackout starts.
    // if I'm currently past the last possible blackout, consider me blacked out so an admin will update the dates.
    $blackouts = \Drupal::config('stanford_earth_r25.adminsettings')->get('stanford_r25_blackout_dates');
    if (empty($blackouts)) {
      $blackouts = [];
    }
    $blacked_out = TRUE;

    // first find out if we are currently in a blackout
    // if not, find out when the next blackout starts
    $cur = time();
    $cur_blackout = TRUE;
    $next_blackout = 0;
    foreach ($blackouts as $blackout) {
      if ($cur < strtotime($blackout['start'])) {
        $cur_blackout = FALSE;
        $next_blackout = strtotime($blackout['start']);
        break;
      }
      else {
        if ($cur <= strtotime($blackout['end'])) {
          break;
        }
      }
    }

    // now see if the requested date is before the next blackout
    if (!$cur_blackout && $date < $next_blackout) {
      $blacked_out = FALSE;
    }
    return $blacked_out;
  }

  public static function _stanford_r25_user_can_cancel_or_confirm($room_id, $event_id, $op) {
    // first get the event's XML from 25Live
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_result = $r25_service->r25_api_call('event-get', $event_id);
    $result = false;
    if ($r25_result['status']['status'] === TRUE) {
      $result = $r25_result['output'];
    }
    if ($result) {
      // make sure the event is for the requested room so nobody pulls a fast one.
      $rooms = [];
      if (!empty($room_id)) {
        $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room_id);
        $rooms[$room_id] = $config->getRawData();
      }

      if ((empty($result['index']['R25:SPACE_ID'])) || (!is_array($result['index']['R25:SPACE_ID'])) ||
        ($result['vals'][$result['index']['R25:SPACE_ID'][0]]['value'] != $rooms[$room_id]['space_id'])
      ) {
        \Drupal::messenger()->addMessage('Room mismatch for confirm or cancel event.',
          MessengerInterface::TYPE_ERROR);
        return FALSE;
      }

      // default is that user can't do operation (confirm or cancel)
      $can_cancel = FALSE;
      global $user;

      // if the user is user1 or has administer rights in Drupal, let them do operation
      $user = \Drupal::currentUser();
      if ($user->id() == 1 || $user->hasPermission('administer stanford r25')) {
        $can_cancel = TRUE;
      }
      else {
        // allow users to cancel events they created, either through this module or directly in 25Live
        if (!empty($user->getEmail()) && $op === 'cancel') {
          // see if requestor email matches or is quickbook. If quickbook, we must check the user's email differently
          $config = \Drupal::configFactory()->getEditable('stanford_earth_r25.credentialsettings');
          $quickbook_id = intval($config->get('stanford_r25_credential_contact_id'));

          // get the R25 user id and email address for the user that scheduled the event.
          $scheduler_id = 0;
          $scheduler_email = '';
          if (!empty($result['index']['R25:ROLE_NAME']) && is_array($result['index']['R25:ROLE_NAME'])) {
            foreach ($result['index']['R25:ROLE_NAME'] as $key => $value) {
              if (!empty($result['vals'][$value]['value']) && $result['vals'][$value]['value'] === 'Scheduler') {
                if (!empty($result['index']['R25:CONTACT_ID'][$key]) &&
                  !empty($result['vals'][$result['index']['R25:CONTACT_ID'][$key]]['value'])
                ) {
                  $scheduler_id = intval($result['vals'][$result['index']['R25:CONTACT_ID'][$key]]['value']);
                  if (!empty($result['index']['R25:EMAIL'][$key]) &&
                    !empty($result['vals'][$result['index']['R25:EMAIL'][$key]]['value'])
                  ) {
                    $scheduler_email = $result['vals'][$result['index']['R25:EMAIL'][$key]]['value'];
                  }
                }
                break;
              }
            }
          }

          if ($scheduler_id > 0) {
            // if the reservation was made with quickbook, we need to pull the requestor's email address out of the event description
            if ($quickbook_id == $scheduler_id) {
              $scheduler_email = '';
              if (!empty($result['index']['R25:TEXT_TYPE_NAME']) && is_array($result['index']['R25:TEXT_TYPE_NAME'])) {
                foreach ($result['index']['R25:TEXT_TYPE_NAME'] as $key => $value) {
                  if (!empty($result['vals'][$value]['value']) && $result['vals'][$value]['value'] === 'Description') {
                    if (!empty($result['index']['R25:TEXT'][$key]) &&
                      !empty($result['vals'][$result['index']['R25:TEXT'][$key]]['value'])
                    ) {
                      $desc = $result['vals'][$result['index']['R25:TEXT'][$key]]['value'];
                      $mailto_pos = strpos($desc, '"mailto:');
                      if ($mailto_pos !== FALSE) {
                        $mailto_endpos = strpos($desc, '"', $mailto_pos + 8);
                        if ($mailto_endpos !== FALSE) {
                          $scheduler_email = substr($desc, $mailto_pos + 8, $mailto_endpos - ($mailto_pos + 8));
                        }
                      }
                    }
                    break;
                  }
                }
              }
            }

            // if the Drupal user's email address is the same as the 25Live scheduler's, then the user can cancel the event.
            if (!empty($scheduler_email) && $user->getEmail() === $scheduler_email) {
              $can_cancel = TRUE;
            }
          }
        }
      }

      // if $can_cancel is false, we should check if the user is in a security group that can still be
      // allowed to cancel or confirm the event
      if (!$can_cancel) {
        // if the room has a security group set for event confirmation, we have the group id stored in the room array
        if (!empty($rooms[$room_id]['approver_secgroup_id'])) {
          // get an array of email addresses for the people in the room's approver security group
          $approvers = StanfordEarthR25Util::_stanford_r25_security_group_emails($rooms[$room_id]['approver_secgroup_id']);
          // see if the Drupal user's email address is in the array of approvers
          $can_cancel = in_array($user->getEmail(), $approvers);
        }
      }

      if ($can_cancel) {
        // if the user can cancel (or confirm) the event, return the event's XML arrays to the caller
        $output = $r25_result;
      }
      else {
        // otherwise just output false
        $output = FALSE;
      }
    }
    else {
      // set an error message if we couldn't contact 25Live.
      \Drupal::messenger()->addMessage('Unable to retrieve data from 25Live. Please try again later.', TYPE_ERROR);
      $output = FALSE;
    }
    return $output;
  }

  // build a comma-delimited string of email addresses associated with a reservation
  public static function _stanford_r25_build_event_email_list($results, $secgroup_id, $extra_list) {

    $user = \Drupal::currentUser();
    // get a list of email addresses for approvers for the event's room's security group
    $mail_array = SELF::_stanford_r25_security_group_emails($secgroup_id);

    // add on any extra email addresses for the room
    if (!empty($extra_list)) {
      $extras = explode(',', $extra_list);
      $mail_array = array_merge($mail_array, $extras);
    }

    // add the current user to the list
    if (!empty($user->getEmail())) {
      $mail_array[] = $user->getEmail();
    }

    // if the event was not done with quickbook, add the scheduler's email to the list
    // if the event *was* done with quickbook, pull the scheduler's email id from the event text
    $config = \Drupal::configFactory()->getEditable('stanford_earth_r25.credentialsettings');
    $quickbook_id = intval($config->get('stanford_r25_credential_contact_id'));
    $quickbook = FALSE;
    if (!empty($results['index']['R25:ROLE_NAME']) && is_array($results['index']['R25:ROLE_NAME'])) {
      foreach ($results['index']['R25:ROLE_NAME'] as $key => $value) {
        if ($results['vals'][$value]['value'] == 'Scheduler') {
          if ($results['vals'][$results['index']['R25:CONTACT_ID'][$key]]['value'] == $quickbook_id) {
            $quickbook = TRUE;
          }
          else {
            $mail_array[] = $results['vals'][$results['index']['R25:EMAIL'][$key]]['value'];
          }
        }
      }
    }
    if ($quickbook) {
      if (!empty($results['index']['R25:TEXT_TYPE_NAME']) && is_array($results['index']['R25:TEXT_TYPE_NAME'])) {
        foreach ($results['index']['R25:TEXT_TYPE_NAME'] as $key => $value) {
          if ($results['vals'][$value]['value'] == 'Description') {
            $desc = $results['vals'][$results['index']['R25:TEXT'][$key]]['value'];
            if (strpos($desc, 'mailto:', 0) !== FALSE) {
              $mailto_start = strpos($desc, 'mailto:', 0) + 7;
              $mailto_end = strpos($desc, '"', $mailto_start);
              $mail_array[] = substr($desc, $mailto_start, $mailto_end - $mailto_start);
            }
          }
        }
      }
    }

    // get rid of duplicate email addresses
    $mail_array = array_unique($mail_array);

    // return the result as a string
    return implode(', ', $mail_array);
  }

}
