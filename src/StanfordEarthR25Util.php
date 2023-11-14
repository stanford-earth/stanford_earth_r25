<?php

namespace Drupal\stanford_earth_r25;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\File\FileRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;

/**
 * Encapsulates information and utility methods.
 */
class StanfordEarthR25Util {

  // Define room status codes as constants.
  const STANFORD_R25_ROOM_STATUS_DISABLED = 0;

  const STANFORD_R25_ROOM_STATUS_READONLY = 1;

  const STANFORD_R25_ROOM_STATUS_TENTATIVE = 2;

  const STANFORD_R25_ROOM_STATUS_CONFIRMED = 3;

  /**
   * Parse a blackout date text area into an array of start and end dates.
   *
   * @param string $inStr
   *   Input string from text area.
   */
  public static function stanfordR25ParseBlackoutDates($inStr = '') {
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

  /**
   * Retrieve the 25Live security group id given the security group name.
   *
   * @param string $secgroup
   *   R25 Security Group Name.
   *
   * @return string
   *   Security group ID number.
   */
  public static function stanfordR25SecgroupId(string $secgroup) {
    $groupid = 0;
    if (!empty($secgroup)) {
      $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
      $r25_result = $r25_service->stanfordR25ApiCall('secgroup', $secgroup);
      if ($r25_result['status']['status'] === TRUE) {
        $results = $r25_result['output'];
        if (!empty($results['vals'][$results['index']['R25:SECURITY_GROUP_ID'][0]]['value'])) {
          $groupid = intval($results['vals'][$results['index']['R25:SECURITY_GROUP_ID'][0]]['value']);
        }
      }
    }
    return $groupid;
  }

  /**
   * Function returns an array of email addresses for a 25Live security group.
   *
   * The result array will be stored in the Drupal cache table unless forced to
   * refresh with the $reset paramater. Savin the room configuration in its
   * admin page will reset this cached data.
   *
   * @param string $secgroup_id
   *   R25 Security Group id.
   * @param bool $reset
   *   Get the list from cache unless $reset = TRUE.
   *
   * @return array
   *   Array of security group email addresses.
   */
  public static function stanfordR25SecurityGroupEmails(string $secgroup_id = NULL, $reset = FALSE) {
    if (empty($secgroup_id)) {
      return [];
    }

    // Get information from cache unless we are resetting it.
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
    $r25_result = $r25_service->stanfordR25ApiCall('r25users', $secgroup_id);
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

  /**
   * Builds URI for room photo pulled from 25Live using directory set in config.
   *
   * @param string $photo_id
   *   The photo_id.
   *
   * @return string
   *   The photo URI.
   */
  public static function stanfordR25FilePath($photo_id) {
    $config = \Drupal::configFactory()
      ->getEditable('stanford_earth_r25.credentialsettings');
    $image_directory = $config->get('stanford_r25_room_image_directory');
    if (empty($image_directory)) {
      $image_directory = '';
    }
    return 'public://' . $image_directory . '/R25_' . $photo_id . '.jpg';
  }

  /**
   * Get room information from its configuration.
   *
   * @param string $space_id
   *   The room id for the reservation.
   *
   * @return array
   *   The room info array.
   */
  public static function stanfordR25GetRoomInfo($space_id = NULL) {
    $room_info = [];
    $default_layout = 0;
    if (!empty($space_id)) {
      // Get the room info using the api.
      $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
      $r25_result = $r25_service->stanfordR25ApiCall('roominfo', $space_id);
      if ($r25_result['status']['status'] === TRUE) {
        $results = $r25_result['output'];
        // Identify default room layout, if defined, else use the 1st one.
        if (!empty($results['index']['R25:DEFAULT_LAYOUT']) && is_array($results['index']['R25:DEFAULT_LAYOUT'])) {
          foreach ($results['index']['R25:DEFAULT_LAYOUT'] as $dlkey => $dlval) {
            if ($results['vals'][$dlval]['value'] == 'T') {
              $default_layout = $dlkey;
              break;
            }
          }
        }
        // Get the room capacity for the layout.
        $room_info['capacity'] = NULL;
        if (!empty($results['index']['R25:LAYOUT_CAPACITY'][$default_layout])) {
          $room_info['capacity'] = $results['vals'][$results['index']['R25:LAYOUT_CAPACITY'][$default_layout]]['value'];
        }
        // Get any comments and instructions about the room.
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
        // Build a list of features for the room layout.
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
        // Build a list of categories for the room.
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
          $room_info['layout_categories'] = $layout_categories;
        }

        // Get a room photo if available and store in Drupal files.
        $photo_id = NULL;
        if (!empty($results['index']['R25:LAYOUT_PHOTO_ID'][$default_layout]) &&
          !empty($results['vals'][$results['index']['R25:LAYOUT_PHOTO_ID'][$default_layout]]['value'])) {
          $photo_id = $results['vals'][$results['index']['R25:LAYOUT_PHOTO_ID'][$default_layout]]['value'];
          $photo_status = $r25_service->stanfordR25ApiCall('roomphoto', $photo_id);
          if ($photo_status['status']['status'] === TRUE) {
            $photo = $photo_status['output'];
            $destination = self::stanfordR25FilePath($photo_id);

            /** @var \Drupal\file\FileRepositoryInterface $file_repository */
            $file_repository = \Drupal::service('file.repository');
            try {
              $file_repository->writeData($photo, $destination, FileSystemInterface::EXISTS_REPLACE);
            }
            catch (InvalidStreamWrapperException $e) {
              \Drupal::messenger()
                ->addMessage('Unable to save image for R25 Location ' . $space_id,
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

  /**
   * Check if current user can view a specific room's calendar.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   Room entity to compare against the current user.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check against override_view_roles for location.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to call alter hooks.
   *
   * @return bool
   *   Boolean indicating the room is viewable.
   */
  public static function stanfordR25CanViewRoom(EntityInterface  $r25_location = NULL,
                                                AccountInterface $account = NULL,
                                                ModuleHandlerInterface $module_handler = NULL) {
    // Check if the user can view the room calendar depending on Drupal
    // permissions and the location's override settings.
    $canView = FALSE;
    $room_id = NULL;
    if (!empty($r25_location)) {
      $room_id = $r25_location->id();
    }
    if (!empty($room_id) && !empty($account)) {
      $canView = $account->hasPermission('view r25 room calendars');
      $roles = $account->getRoles();
      foreach ($roles as $userRole) {
        $uR = Role::load($userRole);
        if (!empty($uR) && $uR->isAdmin()) {
          return true;
        }
      }
      $override_view_roles = $r25_location->get('override_view_roles');
      if (!empty($override_view_roles) && is_array($override_view_roles)) {
        foreach ($override_view_roles as $role => $role_allowed) {
          if (!empty($role) && $role === $role_allowed) {
            $canView = FALSE;
            if (in_array($role, $roles)) {
              $canView = TRUE;
              break;
            }
          }
        }
      }

      // See if any modules want to override this.
      if (!empty($module_handler)) {
        $module_handler->alter(
          'stanford_r25_view_calendar',
          $canView,
          $r25_location);
      }
    }
    return $canView;
  }

  /**
   * Check if current user can book a specific room.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   Room entity to compare against the current user.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check against override_view_roles for location.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to call alter hooks.
   *
   * @return bool
   *   Boolean indicating that the room is bookable by the user.
   */
  public static function stanfordR25CanBookRoom(EntityInterface  $r25_location = NULL,
                                                AccountInterface $account = NULL,
                                                ModuleHandlerInterface $module_handler = NULL) {
    // Check if the user can book the room location depending on Drupal
    // permissions and the location's override settings.
    $canBook = FALSE;
    $room_id = NULL;
    if (!empty($r25_location)) {
      $room_id = $r25_location->id();
    }
    if (!empty($room_id) && !empty($account)) {
      $canBook = $account->hasPermission('book r25 rooms');
      $roles = $account->getRoles();
      foreach ($roles as $userRole) {
        $uR = Role::load($userRole);
        if (!empty($uR) && $uR->isAdmin()) {
          return true;
        }
      }
      $override_book_roles = $r25_location->get('override_book_roles');
      if (!empty($override_book_roles) && is_array($override_book_roles)) {
        foreach ($override_book_roles as $role => $role_allowed) {
          if (!empty($role) && $role === $role_allowed) {
            $canBook = FALSE;
            if (in_array($role, $roles)) {
              $canBook = TRUE;
              break;
            }
          }
        }
      }
      // See if any modules want to override this.
      if (!empty($module_handler)) {
        $module_handler->alter(
          'stanford_r25_book_calendar',
          $canBook,
          $r25_location);
      }
    }
    return $canBook;
  }

  /**
   * Check if given date is blacked out.
   *
   * @param int $date
   *   date to check - given as UNIX timestamp.
   *
   * @return bool
   *   return TRUE if date is blacked out, false otherwise.
   */
  public static function stanfordR25DateBlackedOut($date) {

    // If an empty date is given, return false.
    if (empty($date)) {
      return FALSE;
    }

    // If I'm currently in a blackout period, the requested date doesn't matter
    // - we consider it blacked out.
    // If I'm *not* currently in a blackout period, then the requested date has
    // to be before the next blackout starts.
    // If I'm currently past the last possible blackout, consider me blacked out
    // so an admin will update the dates.
    $blackouts = \Drupal::config('stanford_earth_r25.adminsettings')
      ->get('stanford_r25_blackout_dates');
    if (empty($blackouts)) {
      $blackouts = [];
    }
    $blacked_out = TRUE;

    // First find out if we are currently in a blackout
    // if not, find out when the next blackout starts.
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

    // Now see if the requested date is before the next blackout.
    if (!$cur_blackout && $date < $next_blackout) {
      $blacked_out = FALSE;
    }
    return $blacked_out;
  }

  /**
   * Determine if user can confirm or cancel a reservation.
   *
   * @param string $room_id
   *   The room id for the reservation.
   * @param string $event_id
   *   The R25-generated event id.
   * @param string $op
   *   Whether we are confirming or canceling.
   *
   * @return array
   *   If the user can perform the operation, return info about the event.
   */
  public static function stanfordR25UserCanCancelOrConfirm($room_id, $event_id, $op) {
    // First get the event's XML from 25Live.
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_result = $r25_service->stanfordR25ApiCall('event-get', $event_id);
    $result = FALSE;
    if ($r25_result['status']['status'] === TRUE) {
      $result = $r25_result['output'];
    }
    if ($result) {
      // Ensure the event is for the requested room so nobody pulls a fast one.
      $rooms = [];
      if (!empty($room_id)) {
        $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room_id);
        $rooms[$room_id] = $config->getRawData();
      }

      if ((empty($result['index']['R25:SPACE_ID'])) || (!is_array($result['index']['R25:SPACE_ID'])) ||
        ($result['vals'][$result['index']['R25:SPACE_ID'][0]]['value'] != $rooms[$room_id]['space_id'])
      ) {
        \Drupal::messenger()
          ->addMessage('Room mismatch for confirm or cancel event.',
            MessengerInterface::TYPE_ERROR);
        return FALSE;
      }

      // Default is that user can't do operation (confirm or cancel).
      $can_cancel = FALSE;
      global $user;

      // If the user is user1 or has administer rights in Drupal,
      // let them do operation.
      $user = \Drupal::currentUser();
      if ($user->id() == 1 || $user->hasPermission('administer stanford r25')) {
        $can_cancel = TRUE;
      }
      else {
        // Allow users to cancel events they created, either through this module
        // or directly in 25Live.
        if (!empty($user->getEmail()) && $op === 'cancel') {
          // See if requestor email matches or is quickbook.
          // If quickbook, we must check the user's email differently.
          $config = \Drupal::configFactory()
            ->getEditable('stanford_earth_r25.adminsettings');
          $quickbook_id = intval($config->get('stanford_r25_credential_contact_id'));

          // Get the R25 user id and email address for the event scheduler.
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
            // If the reservation was made with quickbook, we need to pull the
            // requestor's email address out of the event description.
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

            // If the Drupal user's email address is the same as the 25Live
            // scheduler's, then the user can cancel the event.
            if (!empty($scheduler_email) && $user->getEmail() === $scheduler_email) {
              $can_cancel = TRUE;
            }
          }
        }
      }

      // If $can_cancel is false, we should check if the user is in a security
      // group that can still be allowed to cancel or confirm the event.
      if (!$can_cancel) {
        // If the room has a security group set for event confirmation,
        // we have the group id stored in the room array.
        if (!empty($rooms[$room_id]['approver_secgroup_id'])) {
          // Get an array of email addresses for the people in the room's
          // approver security group.
          $approvers = StanfordEarthR25Util::stanfordR25SecurityGroupEmails($rooms[$room_id]['approver_secgroup_id']);
          // See if the user's email address is in the array of approvers.
          $can_cancel = in_array($user->getEmail(), $approvers);
        }
      }

      if ($can_cancel) {
        // If the user can cancel (or confirm) the event,
        // return the event's XML arrays to the caller.
        $output = $r25_result;
      }
      else {
        // Otherwise just output false.
        $output = FALSE;
      }
    }
    else {
      // Set an error message if we couldn't contact 25Live.
      \Drupal::messenger()
        ->addMessage('Unable to retrieve data from 25Live. Please try again later.', TYPE_ERROR);
      $output = FALSE;
    }
    return $output;
  }

  /**
   * Build comma-delimited string of email addrs associated with reservation.
   *
   * @param array $results
   *   Results data from R25 API call.
   * @param string $secgroup_id
   *   R25 security group id.
   * @param string $extra_list
   *   Additional email addresses to attach.
   *
   * @return string
   *   Comma delimited list of email addresses
   */
  public static function stanfordR25BuildEventEmailList(array $results, $secgroup_id, $extra_list) {

    $user = \Drupal::currentUser();
    // Get list of email addresses for approvers of the room's security group.
    $mail_array = self::stanfordR25SecurityGroupEmails($secgroup_id);

    // Add on any extra email addresses for the room.
    if (!empty($extra_list)) {
      $extras = explode(',', $extra_list);
      $mail_array = array_merge($mail_array, $extras);
    }

    // Add the current user to the list.
    if (!empty($user->getEmail())) {
      $mail_array[] = $user->getEmail();
    }

    // If event was not done with quickbook, add scheduler's email to the list.
    // If event *was* done with quickbook, find scheduler's email id in event.
    $config = \Drupal::configFactory()
      ->getEditable('stanford_earth_r25.adminsettings');
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

    // Get rid of duplicate email addresses.
    $mail_array = array_unique($mail_array);

    // Return the result as a string.
    return implode(', ', $mail_array);
  }

  /**
   * Get the calendar limit for the room, in 1-year unless altered by hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   Room entity to compare against the current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to call alter hooks.
   *
   * @return array
   *   Array containing calendar limit info.
   */
  public static function stanfordR25CalendarLimit(EntityInterface $r25_location = NULL,
                                                  ModuleHandlerInterface $module_handler = NULL) {
    // The default calendar limit is for one year in the future, but we have
    // a hook, hook_stanford_r25_fullcalendar_limit_alter(&$calendar_limit)
    // where you can change it.
    $calendar_limit = [
      'room' => $r25_location->toArray(),
      'month' => date('n'),
      'day' => date('d'),
      'year' => strval(intval(date('Y')) + 1),
    ];
    $module_handler->alter('stanford_r25_fullcalendar_limit', $calendar_limit);
    return $calendar_limit;
  }

  /**
   * If using custom event attributes, use the ids to retrieve the name and type
   * of each field.
   *
   * @param string $attr_list
   *   Comma separated string of attribute ids.
   * @param boolean $contact
   *   True if this is a contact attribute, false if this is an event attribute.
   *
   * @return array
   *   The attribute fields array.
   */
  public static function stanfordR25UpdateEventAttributeFields($attr_list, $contact = FALSE) {
    $field_info = [];
    if (!empty($attr_list)) {
      $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
      $attrs = explode(",", $attr_list);
      foreach ($attrs as $attr) {
        $attr_id = trim($attr);
        if (substr($attr_id, -1) == '*') {
          $attr_id = substr($attr_id, 0, strlen($attr_id) - 1);
        }
        $r25_result = $r25_service->stanfordR25ApiCall('evatrb', $attr_id);
        if ($r25_result['status']['status'] === TRUE) {
          $results = $r25_result['output'];
          $attrName = '';
          $attrType = '';
          if (!empty($results['index']['R25:ATTRIBUTE_NAME']) &&
            is_array($results['index']['R25:ATTRIBUTE_NAME'])) {
            $attrNameIdx = reset($results['index']['R25:ATTRIBUTE_NAME']);
            if (!empty($attrNameIdx) &&
              !empty($results['vals'][$attrNameIdx]['value'])) {
              $attrName = $results['vals'][$attrNameIdx]['value'];
            }
          }
          if (!empty($results['index']['R25:ATTRIBUTE_TYPE']) &&
            is_array($results['index']['R25:ATTRIBUTE_TYPE'])) {
            $attrTypeIdx = reset($results['index']['R25:ATTRIBUTE_TYPE']);
            if (!empty($attrTypeIdx) &&
              !empty($results['vals'][$attrTypeIdx]['value'])) {
              $attrType = $results['vals'][$attrTypeIdx]['value'];
            }
          }
          if (!empty($attrName) && !empty($attrType)) {
            $field_info[$attr_id] = [
              'name' => $attrName,
              'type' => $attrType,
              'contact' => $contact,
            ];
          }
        }
      }
    }
    return $field_info;
  }

}
