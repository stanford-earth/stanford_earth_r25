<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystem;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contains Drupal\stanford_earth_r25\Form\StanfordEarthR25ReservationForm.
 */
class StanfordEarthR25ReservationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_r25_reservation';
  }

  private function parseDateStr($dateStr) {
    $dayParts = [];
    $dayBits = explode('-',$dateStr);
    if (!empty($dayBits) && count($dayBits) > 4) {
      $dayParts['year'] = $dayBits[0];
      $dayParts['month'] = $dayBits[1];
      $dayParts['day'] = $dayBits[2];
      $dayParts['hour'] = $dayBits[3];
      $minutes = intval($dayBits[4]);
      if ($minutes > 0 && $minutes < 30) {
        $minutes = 30;
      }
      else {
        if ($minutes > 30) {
          $minutes = 0;
          $hour = intval($dayParts['hour']) + 1;
          if ($hour == 24) {
            $hour = 0;
          }
          $dayParts['hour'] = $hour;
        }
      }
      $dayParts['minute'] = $minutes;
      $dayParts['seconds'] = 0;
      if (count($dayBits) > 5) {
        $dayParts['extra1'] = $dayBits[5];
        $extra2str = '';
        for ($i=6; $i<count($dayBits); $i++) {
          if ($i > 6) {
            $extra2str .= '-';
          }
          $extra2str .= $dayBits[$i];
        }
        $dayParts['extra2'] = $extra2str;
      }
    }
    return $dayParts;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $room = NULL, $start = NULL) {
    $rooms = [];
    $adminSettings = [];
    if (!empty($room)) {
      $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room);
      $rooms[$room] = $config->getRawData();
      $config = \Drupal::config('stanford_earth_r25.adminsettings');
      $adminSettings = $config->getRawData();
    }
    $form['#prefix'] = '<div id="modal_reservation_form">';
    $form['#suffix'] = '</div>';

    // AJAX messages.
    $form['stanford_r25_ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'stanford-r25-ajax-messages',
      ],
    ];

    // keep the roomid for later processing
    $form['stanford_r25_booking_roomid'] = array(
      '#type' => 'hidden',
      '#value' => $room,
    );

    // if the room only accepts tentative bookings, then put up a message to that effect
    if (!empty($rooms[$room]['status']) && intval($rooms[$room]['status']) == StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_TENTATIVE ) {
        $form['stanford_r25_booking_tentative'] = array(
          '#type' => 'markup',
          '#markup' => "<p>This room only accepts tentative reservations which must be approved by the room's administrator.</p>",
        );
      }

    // use the Drupal date popup for date and time picking
    $dayParts = [];
    $endParts = [];
    $durationIndex = -1;
    if (!empty($start) && $start !== 'now') {
      $dayParts = $this->parseDateStr($start);
      if (!empty($dayParts['extra1']) && !empty($dayParts['extra2'])) {
        if ($dayParts['extra1'] === 'duration') {
          $durationIndex = $dayParts['extra2'];
        }
        else if ($dayParts['extra1'] === 'end') {
          $endParts = $this->parseDateStr($dayParts['extra2']);
        }
      }
      unset($dayParts['extra1']);
      unset($dayParts['extra2']);
    } else {
      $daytime = DrupalDateTime::createFromTimestamp(time());
      $daytimestr = $daytime->format('Y-m-d-H-i');
      $dayParts = $this->parseDateStr($daytimestr);
      $endParts = $dayParts;
    }
       $form['stanford_r25_booking_date'] = array(
        '#type' => 'datetime',
        '#default_value' => DrupalDateTime::createFromArray($dayParts),
        '#required' => TRUE,
        '#title' => 'Start Date/Time',
      );

      if (empty($rooms[$room]['multi_day'])) {
        // for non-multi-day rooms. default booking duration is limited to 2 hours
        // in 30 minute increments, but the room config can have a different value.
        // A value of 0 hours is the same as a value of 24 hours.
        $max_hours = 2;
        if (!empty($rooms[$room]['max_hours'])) {
          $max_hours = intval($rooms[$room]['max_hours']);
          if ($max_hours == 0) {
            $max_hours = 24;
          }
        }
        $hours_array = array();
        if ($max_hours > 2) {
          for ($i = 0; $i < $max_hours; $i++) {
            $hstr = '';
            if ($i == 0) {
              $hours_array[] = '30 minutes';
              $hours_array[] = '1 hour';
            }
            else {
              $hours_array[] = strval($i) . '.5 hours';
              $hours_array[] = strval($i + 1) . ' hours';
            }
          }
        }
        else {
          $hours_array[0] = '30 minutes';
          $hours_array[1] = '60 minutes';
          if ($max_hours > 1) {
            $hours_array[2] = '90 minutes';
            $hours_array[3] = '120 minutes';
          }
        }
        $form['stanford_r25_booking_duration'] = array(
          '#type' => 'select',
          '#title' => t('Duration'),
          '#options' => $hours_array,
          '#default_value' => $durationIndex,
          '#required' => TRUE,
        );
      }
      else {
        // multi-day rooms have an end date and time instead of duration
        $max_hours = '';
        $form['stanford_r25_booking_enddate'] = array(
          '#type' => 'datetime',
          '#default_value' => DrupalDateTime::createFromArray($endParts),
          '#required' => TRUE,
          '#title' => 'End Date/Time',
        );
      }
      // max headcount for a room comes from parameter passed to the function
      $form['stanford_r25_booking_headcount'] = array(
        '#type' => 'select',
        '#title' => t('Headcount'),
        '#options' => array(),
        '#required' => TRUE,
      );
      $max_headcount = 5;
      if (!empty($rooms[$room]['location_info']['capacity'])) {
        $max_headcount = $rooms[$room]['location_info']['capacity'];
      }
      // add to the select list for the number of possible headcounts
      for ($i = 1; $i < $max_headcount + 1; $i++) {
        $form['stanford_r25_booking_headcount']['#options'][] = strval($i);
      }
      // every booking needs some reason text
      $form['stanford_r25_booking_reason'] = array(
        '#type' => 'textfield',
        '#title' => t('Reason'),
        '#required' => TRUE,
        '#maxlength' => 40,
      );

      // check for event attribute fields, and build 'em
      // each of these corresponds to a "custom attribute" for events in 25Live
      // and are specified in our room config as an array of attrib ids, field name,
      // and field type
      if (!empty($rooms[$room]['event_attribute_fields'])) {
        foreach ($rooms[$room]['event_attribute_fields'] as $attr_id => $attr_info) {
          switch ($attr_info['type']) {
            case 'S':
              $field_type = 'textfield';
              break;
            case 'B':
              $field_type = 'checkbox';
              break;
            case 'X':
              $field_type = 'textarea';
              break;
            default:
              $field_type = '';
          }
          if (!empty($field_type)) {
            $form['stanford_r25_booking_attr' . $attr_id] = array(
              '#type' => $field_type,
              '#title' => $attr_info['name'],
            );
          }
        }
      }

      // in the room config, we can separately specify a contact field attribute
      if (!empty($rooms[$room]['contact_attr_field'])) {
        foreach ($rooms[$room]['contact_attr_field'] as $attr_id => $attr_info) {
          switch ($attr_info['type']) {
            case 'S':
              $field_type = 'textfield';
              break;
            case 'B':
              $field_type = 'checkbox';
              break;
            case 'X':
              $field_type = 'textarea';
              break;
            default:
              $field_type = '';
          }
          if (!empty($field_type)) {
            $user = \Drupal::currentUser();
            $contact_info = $user->getDisplayName() . "\r\n" .
              $user->getEmail() . "\r\n";
            $form['stanford_r25_contact_' . $attr_id] = array(
              '#type' => $field_type,
              '#title' => $attr_info['name'],
              '#default_value' => $contact_info,
            );
          }
        }
      }

      //TBD
      // if the user making the reservation has authenticated in some external
      // fashion (is not logged in as a Drupal user) then we should have been
      // passed the user's displayname and email address in the function call.
      // save them as hidden fields on the reservation form.
      if (!empty($external_acct) && is_array($external_acct)) {
        if (!empty($external_acct['R25_EXTERNAL_DISPLAYNAME']) &&
          is_string($external_acct['R25_EXTERNAL_DISPLAYNAME'])
        ) {
          $form['external_username'] = array(
            '#type' => 'hidden',
            '#value' => $external_acct['R25_EXTERNAL_DISPLAYNAME'],
          );
        }
        if (!empty($external_acct['R25_EXTERNAL_MAIL']) &&
          is_string($external_acct['R25_EXTERNAL_MAIL'])
        ) {
          $form['external_usermail'] = array(
            '#type' => 'hidden',
            '#value' => $external_acct['R25_EXTERNAL_MAIL'],
          );
        }
      }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reserve'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    // display some reservation instructions if available, replacing
    // the [max_duration] tag with the room's maximum meeting duration.
    // Use the site-wide booking instruction unless the room as an override.
    if (!empty($rooms[$room]['override_booking_instructions']['value'])) {
      $booking_instr = $rooms[$room]['override_booking_instructions'];
    }
    else {
      $booking_instr = [
        'value' => '',
        'format' => null
      ];
      if (!empty($adminSettings['stanford_r25_booking_instructions']['value'])) {
        $booking_instr = $adminSettings['stanford_r25_booking_instructions'];
      }
    }
    if (!empty($booking_instr['value'])) {
      $booking_instr['value'] = str_replace('[max_duration]', $max_hours, $booking_instr['value']);
    }
    $form['r25_instructions'] = array(
      '#type' => 'markup',
      '#markup' => check_markup($booking_instr['value'], $booking_instr['format']),
    );

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $error_list = '<ul>';
      foreach ($form_state->getErrors() as $error_field => $error_value) {
        $error_list .= '<li>' . $error_value->render() . '</li>';
      }
      $error_list .= '</ul>';
      $form_state->clearErrors();
      \Drupal::messenger()->deleteAll();
      $response->addCommand(new HtmlCommand('#stanford-r25-ajax-messages', $error_list));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();
    if (empty($user_input['stanford_r25_booking_roomid'])) {
      $form_state->setErrorByName('stanford_r25_booking_roomid',
        new TranslatableMarkup('The room id is missing from the request.'));
      return;
    }
    $room = $user_input['stanford_r25_booking_roomid'];
    $booking_info = array();  // store booking info in form storage after validation
    $rooms = [];
    $adminSettings = [];
    if (!empty($room)) {
      $rooms[$room] = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room)->getRawData();
      $adminSettings = \Drupal::config('stanford_earth_r25.adminsettings')->getRawData();
    }

    // make sure we have a valid room id in the form input
    if (!isset($rooms[$room])) {
      $form_state->setErrorByName('stanford_r25_booking_roomid',
        new TranslatableMarkup('The reservation room id is invalid.'));
      return;
    }
    else {
      $booking_info['room'] = $rooms[$room];
    }

    // make sure the current user has permission to book the room
    $entity = \Drupal::entityTypeManager()->getStorage('stanford_earth_r25_location')
      ->load($room);
    $can_book = StanfordEarthR25Util::_stanford_r25_can_book_room($entity);
    if (!$can_book['can_book']) {
      $form_state->setErrorByName('stanford_r25_booking_reason',
        new TranslatableMarkup('User does not have permission to book rooms.'));
      return;
    }

    // make sure we have a valid date
    // some date and time formatting stuff - taking input from form date/time and duration fields
    // and returning start and end times in W3C format to pass to the 25Live web services api.
    $booking_date = $user_input['stanford_r25_booking_date'];
    $booking_str = $booking_date['date'] . '-' . $booking_date['time'];
    $booking_str = str_replace(':','-',$booking_str);
    $date = DrupalDateTime::createFromArray($this->parseDateStr($booking_str));
    $date2 = $date->format('Y-m-d g:i a');
    if (empty($date) || $date->hasErrors()) {
      $form_state->setErrorByName('stanford_r25_booking_date',
        new TranslatableMarkup('The start date is invalid.'));
      return;
    }

    // don't allow reservations more than 1/2 hour in the past. we're not a time machine.
    if ($date->getTimestamp() < (time() - 1800)) {
      $form_state->setErrorByName('stanford_r25_booking_date',
        new TranslatableMarkup('A reservation in the past was requested. This isn\'t a time machine!'));
      return;
    }

    // if this is a multi-day capable room, check the end date the same way we just checked
    // the start date, and make sure it isn't early than the start date.
    $end_date = null;
    if (!empty($user_input['stanford_r25_booking_enddate'])) {
      $booking_end_date = $user_input['stanford_r25_booking_enddate'];
      $booking_str = $booking_end_date['date'] . '-' . $booking_end_date['time'];
      $booking_str = str_replace(':', '-', $booking_str);
      $end_date = DrupalDateTime::createFromArray($this->parseDateStr($booking_str));
      //$date2 = $date->format('Y-m-d g:i a');
      if (empty($end_date) || $end_date->hasErrors()) {
        $form_state->setErrorByName('stanford_r25_booking_enddate',
          new TranslatableMarkup('The end date is invalid.'));
        return;
      }
      if ($end_date->getTimestamp() <= $date->getTimestamp()) {
        $form_state->setErrorByName('stanford_r25_booking_enddate',
          new TranslatableMarkup('The end date may not be before the start date.'));
        return;
      }
    }

    // make sure date isn't blacked out if room checks for that
    if ($rooms[$room]['honor_blackouts'] == 1) {
      if (StanfordEarthR25Util::_stanford_r25_date_blacked_out($date->getTimestamp()) ||
        (!empty($end_date) && StanfordEarthR25Util::_stanford_r25_date_blacked_out($end_date->getTimestamp()))
      ) {
        $form_state->setErrorByName('stanford_r25_booking_date',
          new TranslatableMarkup('This room is unavailable for reservation on the requested date. ' .
            'The room may only be reserved until the end of the current quarter. Please see your department ' .
            'administrator for more information.'));
        return;
      }
    }

    // build 25Live date strings
    if (!empty($end_date)) {
      $date_strs = array(
        'day' => $date->format('Y-m-d'),
        'start' => $date->format(DATE_W3C),
        'end' => $end_date->format(DATE_W3C),
      );
    }
    else {
      $duration = intval($user_input['stanford_r25_booking_duration']);
      if ($duration < 0 || $duration > (($booking_info['room']['max_hours'] * 2) - 1)) {
        $form_state->setErrorByName('stanford_r25_booking_duration',
          new TranslatableMarkup('The reservation duration is invalid.'));
        return;
      }
      $duration = ($duration * 30) + 30;
      $date_strs = array(
        'day' => $date->format('Y-m-d'),
        'start' => $date->format(DATE_W3C),
        'end' => $date->add(new \DateInterval('PT' . $duration . 'M'))->format(DATE_W3C),
      );
    }

    // store booking info in form storage
    $booking_info['dates'] = $date_strs;
    $form_state->setStorage($booking_info);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $xyz = 1;
    /*
         // as mentioned above, when the user submits a reservation requests, save the date and calendar view to cookies
   $('#stanford-r25-reservation').submit(function (event) {
       var view = $('#calendar').fullCalendar('getView');
       document.cookie = "stanford-r25-view=" + view.name;
       document.cookie = "stanford-r25-date=" + $('#edit-stanford-r25-booking-date-datepicker-popup-0').val();
       return true;
   });

  */

  }

}
