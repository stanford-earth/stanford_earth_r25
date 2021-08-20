<?php

namespace Drupal\stanford_earth_r25;

use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;

/**
 * Encapsulates information and utility methods.
 */
class StanfordEarthR25Util {

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
        $room_info['capacity'] = empty($results['index']['R25:LAYOUT_CAPACITY'][$default_layout]) ? NULL :
          $results['vals'][$results['index']['R25:LAYOUT_CAPACITY'][$default_layout]]['value'];
        // get any comments and instructions about the room
        $room_info['comments'] = empty($results['index']['R25:COMMENTS'][0]) ? NULL : $results['vals'][$results['index']['R25:COMMENTS'][0]]['value'];
        $room_info['layout_name'] = empty($results['index']['R25:LAYOUT_NAME'][$default_layout]) ? NULL :
          $results['vals'][$results['index']['R25:LAYOUT_NAME'][$default_layout]]['value'];
        $room_info['layout_instruction'] = empty($results['index']['R25:LAYOUT_INSTRUCTION'][$default_layout]) ? NULL :
          empty($results['vals'][$results['index']['R25:LAYOUT_INSTRUCTION'][$default_layout]]['value']) ? NULL :
            $results['vals'][$results['index']['R25:LAYOUT_INSTRUCTION'][$default_layout]]['value'];
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
        $room_info['layout_features'] = empty($layout_features) ? NULL : $layout_features;
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
        $room_info['layout_categories'] = empty($layout_categories) ? NULL : $layout_categories;

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
              \Drupal::messenger()->addMessage('Unable to save image for R25 Location ' . $space_id, TYPE_ERROR);
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
}
