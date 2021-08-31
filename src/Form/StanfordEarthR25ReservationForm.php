<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\Core\Ajax\CloseModalDialogCommand;
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
use Drupal\Core\Ajax\InvokeCommand;
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

    // display some reservation instructions if available, replacing
    // the [max_duration] tag with the room's maximum meeting duration.
    // Use the site-wide booking instruction unless the room as an override.
    $max_hours = 2;
    if (!empty($rooms[$room]['max_hours'])) {
      $max_hours = intval($rooms[$room]['max_hours']);
      if ($max_hours == 0) {
        $max_hours = 24;
      }
    }

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

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $r25_messages = [];
    if (!empty($storage['stanford_earth_r25']['r25_messages'])) {
      $r25_messages = $storage['stanford_earth_r25']['r25_messages'];
    }
    $form_state->setStorage([]);

    $response = new AjaxResponse();
    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $error_list = '<ul style="color:red">';
      foreach ($form_state->getErrors() as $error_field => $error_value) {
        $error_list .= '<li>' . $error_value->render() . '</li>';
      }
      $error_list .= '</ul>';
      $form_state->clearErrors();
      \Drupal::messenger()->deleteAll();
      $response->addCommand(new HtmlCommand('#stanford-r25-ajax-messages', $error_list));
      $response->addCommand(new InvokeCommand('#drupal-modal', 'scrollTop', [0]));
    }
    else if (!empty($r25_messages))
    {
      if (count($r25_messages) == 1) {
        $msg_list = '<span style="color:red">' . $r25_messages[0] . '</span>';
      }
      else {
        $msg_list = '<ul style="color:red">';
        foreach ($r25_messages as $r25_message) {
          $msg_list .= '<li>' . $r25_message . '</li>';
        }
        $msg_list .= '</ul>';
      }
      $msg = new TranslatableMarkup($msg_list);
      $response->addCommand(new OpenModalDialogCommand("Booking Result", $msg->render(), ['width' => 800]));
      $response->addCommand(new InvokeCommand(null, 'stanfordEarthR25Refresh'));
    }
    else {
      $response->addCommand(new CloseModalDialogCommand());
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
    if (!empty($room)) {
      $rooms[$room] = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room)->getRawData();
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
    $storage = ['stanford_earth_r25' =>
      ['booking_info' => $booking_info]];
    $form_state->setStorage($storage);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');

    $storage = $form_state->getStorage();
    $r25_messages = [];
    $booking_info = $storage['stanford_earth_r25']['booking_info'];
    // make sure the user has access, that the needed information is available, and the room is bookable
    if (empty($booking_info['dates']) || empty($booking_info['room'])) {
      $r25_messages[] = new TranslatableMarkup('<span style="color:red;">Insufficient booking information was provided.</span>');
      $storage['stanford_earth_r25']['r25_messages'] = $r25_messages;
      $form_state->setStorage($storage);
      return;
    }

    $event_state = intval($booking_info['room']['displaytype']);
    if ($event_state < StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_TENTATIVE ||
      $event_state > StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_CONFIRMED) {
      $r25_messages[] = new TranslatableMarkup('<span style="color:red;">This room may not be reserved through this website.</span>');
      $storage['stanford_earth_r25']['r25_messages'] = $r25_messages;
      $form_state->setStorage($storage);
      return;
    }

    $entity = \Drupal::entityTypeManager()->getStorage('stanford_earth_r25_location')
      ->load($booking_info['room']['id']);
    $can_book = StanfordEarthR25Util::_stanford_r25_can_book_room($entity);
    if (!$can_book['can_book']) {
      $r25_messages[] = new TranslatableMarkup('<span style="color:red;">You do not have permission to book this room.</span>');
      $storage['stanford_earth_r25']['r25_messages'] = $r25_messages;
      $form_state->setStorage($storage);
      return;
    }

    $adminSettings = \Drupal::config('stanford_earth_r25.adminsettings')->getRawData();

    $mail_list = '';  // we'll build a list of email addresses to send reservation info to

    // tentative reservations will generate 25Live "to do tasks" for approvers if the room
    // has an approver security group id associated with it.
    $todo_insert = '';
    if ($event_state == STANFORD_R25_ROOM_STATUS_TENTATIVE &&
      !empty($booking_info['room']['approver_secgroup_id'])
    )
    {
      // get the list of email addresses for the security group that can do the approvals
      $approver_list =
        StanfordEarthR25Util::_stanford_r25_security_group_emails($booking_info['room']['approver_secgroup_id']);
      if (!empty($approver_list)) {
        // for each approver in the security group, add their email address to the list and
        // create a "to do task" XML snippet with their 25Live id and the event information
        // to be added to the request XML.
        $contact_id = '';
        if (!empty($adminSettings['stanford_r25_credential_contact_id'])) {
          $contact_id = $adminSettings['stanford_r25_credential_contact_id'];
        }
        $todo_str = file_get_contents(drupal_get_path('module', 'stanford_earth_r25') .
          '/templates/stanford_r25_reserve_todo.xml');
        foreach ($approver_list as $key => $value) {
          $todo_temp = str_replace('[r25_start_date_time]', $booking_info['dates']['start'], $todo_str);
          $todo_temp = str_replace('[r25_approver_id]', $key, $todo_temp);
          $todo_temp = str_replace('[r25_credential_id]', $contact_id, $todo_temp);
          $todo_insert .= $todo_temp;
          if (!empty($mail_list)) {
            $mail_list .= ', ';
          }
          $mail_list .= $value;
        }
      }
    }

    // if there are 25Live custom attributes associated with this event, add the XML snippet for each attribute
    // with its id, type, and value to the request XML.
    $attr_insert = '';
    $attr_str = file_get_contents(drupal_get_path('module', 'stanford_earth_r25') .
      '/templates/stanford_r25_reserve_attr.xml');
    $room = $booking_info['room'];
    $form_vals = $form_state->getValues();
    if (!empty($room['event_attribute_fields'])) {
      foreach ($room['event_attribute_fields'] as $key => $value) {
        if (!empty($form_vals['stanford_r25_booking_attr' . $key])) {
          $attr_temp = str_replace('[r25_attr_id]', $key, $attr_str);
          $attr_temp = str_replace('[r25_attr_type]', $value['type'], $attr_temp);
          $attr_temp = str_replace('[r25_attr_value]', $form_vals['stanford_r25_booking_attr' . $key], $attr_temp);
          $attr_insert .= $attr_temp;
        }
      }
    }
    // if there is a 25Live custom attribute we've defined for contact information, add the XML snippet for it to the request
    if (!empty($room['contact_attr_field'])) {
      foreach ($room['contact_attr_field'] as $key => $value) {
        if (!empty($form_vals['stanford_r25_contact_' . $key])) {
          $attr_temp = str_replace('[r25_attr_id]', $key, $attr_str);
          $attr_temp = str_replace('[r25_attr_type]', $value['type'], $attr_temp);
          $attr_temp = str_replace('[r25_attr_value]', $form_vals['stanford_r25_contact_' . $key], $attr_temp);
          $attr_insert .= $attr_temp;
        }
      }
    }

    // get the XML template for creating an event and replace tokens with data for this reservation
    $event_state = $event_state - 1;
    $xml_file = '/templates/stanford_r25_reserve.xml';
    $xml = file_get_contents(drupal_get_path('module', 'stanford_earth_r25') . $xml_file); //'/stanford_r25_reserve.xml');
    $xml = str_replace('[r25_event_name]', $form_vals['stanford_r25_booking_reason'], $xml);
    $parent_id = 'unknown';
    if (!empty($adminSettings['stanford_r25_parent_event_id'])) {
      $parent_id = $adminSettings['stanford_r25_parent_event_id'];
    }
    $xml = str_replace('[r25_parent_id]', $parent_id, $xml);
    $event_type = 'unknown';
    if (!empty($adminSettings['stanford_r25_event_type'])) {
      $event_type = $adminSettings['stanford_r25_event_type'];
    }
    $xml = str_replace('[r25_event_type]', $event_type, $xml);
    $xml = str_replace('[r25_event_state]', $event_state, $xml);
    $org_id = 'unknown';
    if (!empty($adminSettings['stanford_r25_org_id'])) {
      $org_id = $adminSettings['stanford_r25_org_id'];
    }
    $xml = str_replace('[r25_organization_id]', $org_id, $xml);
    $xml = str_replace('[r25_expected_headcount]', $form_state->getCompleteForm()['stanford_r25_booking_headcount']['#options'][$form_vals['stanford_r25_booking_headcount']], $xml);
    $xml = str_replace('[r25_start_date_time]', $booking_info['dates']['start'], $xml);
    $xml = str_replace('[r25_end_date_time]', $booking_info['dates']['end'], $xml);
    $xml = str_replace('[r25_space_id]', $booking_info['room']['space_id'], $xml);
    $xml = str_replace('[r25_todo]', $todo_insert, $xml);
    $xml = str_replace('[r25_attr]', $attr_insert, $xml);

    // we want to put some information about the user making this request into the event description to be displayed on the calendar
    $user = \Drupal::currentUser();
    $contact_info = $user->getDisplayName() . "\r\n" .
      $user->getEmail() . "\r\n";
    $res_username = '';
    $res_usermail = '';
    if ($user->id() === 0) {
      $user_input = $form_state->getUserInput();
      if (!empty($user_input['external_username'])) {
        $res_username = $user_input['external_username'];
      }
      if (!empty($user_input['external_usermail'])) {
        $res_usermail = $user_input['external_usermail'];
      }
    }
    else {
      $res_username = $user->getDisplayName();
      $res_usermail = $user->getEmail();
    }
    $contact_str = '<p>Self service reservation made by ' . $res_username . ' - <a href="mailto:' . $res_usermail . '">click to contact by email.</a></p>';
    $contact_str = htmlspecialchars($contact_str);
    // send the request to our api function'
    $xml = str_replace('[r25_created_by]', $contact_str, $xml);
    // send the request to our api function
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_result = $r25_service->r25_api_call('reserve', $xml);
    // check the results to see if our reservation attempt was successful
    $success = false;
    if ($r25_result['status']['status'] === true) {
      $result = $r25_result['output'];
      // a successful return with no status message is assumed to be a success since that's how the webservices api works. go figure.
      // if we use the setting that returns a positive return code for success, then other information is missing.
      if (empty($result['index']['R25:MSG_ID'][0])) {
        // check if the result has the location and time we requested
        if (!empty($result['index']['R25:SPACE_ID'][0]) &&
          $result['vals'][$result['index']['R25:SPACE_ID'][0]]['value'] == $booking_info['room']['space_id'] &&
        !empty($result['index']['R25:EVENT_START_DT'][0]) &&
        $result['vals'][$result['index']['R25:EVENT_START_DT'][0]]['value'] == $booking_info['dates']['start'] &&
        !empty($result['index']['R25:EVENT_END_DT'][0]) &&
        $result['vals'][$result['index']['R25:EVENT_END_DT'][0]]['value'] == $booking_info['dates']['end']
        ) {
          $success = TRUE;
        }
      }
      else {
        // even though we should not see a success code, we do want to check if we got a failure code, which is anything but the
        // two defined success codes.
        $msg_index = $result['index']['R25:MSG_ID'][0];
        if (!empty($result['vals'][$msg_index]['value'])) {
          if ($result['vals'][$msg_index]['value'] === 'EV_I_SAVE' ||
            $result['vals'][$msg_index]['value'] === 'EV_I_CREATED'
          ) {
            $success = TRUE;
          }
        }
      }
    }

    // if the reservation request was successful, we want to add any billing
    // information to the request if defined, display a success message on the
    // page, and send an email to any approvers or others specified.
    if ($success) {

      // if the booking was successful, format and display a message to that effect
      $date = DrupalDateTime::createFromFormat(DATE_W3C, $booking_info['dates']['start']);
      $state = intval($result['vals'][$result['index']['R25:STATE'][0]]['value']);
      $msg = $booking_info['room']['label'] . ' has a <b>' . $result['vals'][$result['index']['R25:STATE_NAME'][0]]['value'] . '</b> reservation for "' . $form_vals['stanford_r25_booking_reason'] . '" on  ' . $date->format("l, F j, Y g:i a") . '.';
      if (intval($result['vals'][$result['index']['R25:STATE'][0]]['value']) == 1) {
        $msg .= ' The room administrator will confirm or deny your request.';
      }
      $r25_messages[] = new TranslatableMarkup('<span style="color:green;">' . $msg . '</span>');
      $storage['stanford_earth_r25']['r25_messages'] = $r25_messages;
      $form_state->setStorage($storage);

      // if this event is billable, we have to retrieve billing XML for the event, update
      // the billing group code, and PUT the XML back to the 25Live system.
      $estimated_charge = 0;
      $billable = FALSE;
      $eventid = $result['vals'][$result['index']['R25:EVENT_ID'][0]]['value'];
      if (!empty($booking_info['room']['auto_billing_code'])) {
        $bill_code = $booking_info['room']['auto_billing_code'];
        $billable = TRUE;
        // TBD replace drupal_alter with Module Handler
        //drupal_alter('stanford_r25_isbillable', $billable);
        if ($billable) {
          $r25_result = $r25_service->r25_api_call('billing-get', $eventid);
          $billing_xml = $r25_result['raw-xml'];
          $est_ptr = strpos($billing_xml, 'status="est"');
          $billing_xml = substr($billing_xml, 0, $est_ptr) . 'status="mod"' . substr($billing_xml, $est_ptr + 12);
          $space_code = strpos($billing_xml, $booking_info['room']['space_id']);
          $bill_tmp = substr($billing_xml, 0, $space_code);
          $est_ptr = strrpos($bill_tmp, 'status="est"');
          $billing_xml = substr($billing_xml, 0, $est_ptr) . 'status="mod"' . substr($billing_xml, $est_ptr + 12);
          $est_ptr = strpos($billing_xml, '<r25:rate_group_id/>', $space_code);
          $billing_xml = substr($billing_xml, 0, $est_ptr) . '<r25:rate_group_id>' . $bill_code . '</r25:rate_group_id>' .
            substr($billing_xml, $est_ptr + 20);

          $history_ptr = strpos($billing_xml, '<r25:history_type_id>4');
          $est_ptr = strrpos(substr($billing_xml, 0, $history_ptr), '"est"');
          $billing_xml = substr($billing_xml, 0, $est_ptr) . '"mod"' . substr($billing_xml, $est_ptr + 5);
          $hist_dt_ptr1 = strpos($billing_xml, '<r25:history_dt>', $history_ptr);
          $hist_dt_ptr2 = strpos($billing_xml, '</r25:history_dt>', $history_ptr);
          $billing_xml = substr($billing_xml, 0, $hist_dt_ptr1 + 16) .
            $booking_info['dates']['start'] . substr($billing_xml, $hist_dt_ptr2);
          $r25_results = $r25_service->r25_api_call('billing-put', $billing_xml, $eventid);
          if ($r25_results['status']['status']) {
            $result = $r25_results['output'];
            if (!empty($result['index']['R25:BILL_ITEM_TYPE_NAME']) &&
              is_array($result['index']['R25:BILL_ITEM_TYPE_NAME'])
            ) {
              foreach ($result['index']['R25:BILL_ITEM_TYPE_NAME'] as $key => $value) {
                if (!empty($result['vals'][$value]['value']) &&
                  $result['vals'][$value]['value'] === 'GRAND TOTAL'
                ) {
                  if (!empty($result['index']['R25:TOTAL_CHARGE'][$key])) {
                    $key2 = $result['index']['R25:TOTAL_CHARGE'][$key];
                    if (!empty($result['vals'][$key2]['value'])) {
                      $estimated_charge = intval($result['vals'][$key2]['value']);
                    }
                  }
                  break;
                }
              }
            }
          }
        }
      }

      // send an email about the booking if mail list is set
      if (!empty($mail_list) && !empty($booking_info['room']['email_list'])) {
        $mail_list .= ', ';
      }
      $mail_list .= $booking_info['room']['email_list'];
      $body = array();
      $body[] = "A " . $result['vals'][$result['index']['R25:STATE_NAME'][0]]['value'] . " reservation has been made";
      $subject = '';
      if ($state == 1) {
        // this is the email for a tentative booking
        $subject = 'Room Reservation Request - ACTION REQUIRED';
        $body[0] .= ' requiring your approval.';
        $body[] = 'You may view this request in 25Live and confirm or deny it at this link (requires you first be logged in to 25Live): ';
        $body[] = 'https://25live.collegenet.com/stanford/#details&obj_type=event&obj_id=' . $result['vals'][$result['index']['R25:EVENT_ID'][0]]['value'];
        $body[] = '';
      }
      else if ($state == 2) {
        // this is the email for a confirmed booking
        $subject = 'Room Reservation';
        $body[0] .= '.';
        $body[] = 'View the reservation at: https://25live.collegenet.com/stanford/#details&obj_type=event&obj_id=' . $result['vals'][$result['index']['R25:EVENT_ID'][0]]['value'];
      }

      $body[] = "Room: " . $booking_info['room']['label'];
      if (!empty($form_vals['stanford_r25_booking_duration'])) {
        $body[] = "Date: " . $date->format("l, F j, Y g:i a");
        $duration = (intval($form_vals['stanford_r25_booking_duration']) * 30) + 30;
        if ($duration > 120) {
          $body[] = 'Duration: ' . $duration / 60 . ' hours';
        }
        else {
          $body[] = 'Duration: ' . $duration . ' minutes';
        }
      }
      else {
        $body[] = "Start Date: " . $date->format("l, F j, Y g:i a");
        $enddate = DrupalDateTime::createFromFormat(DATE_W3C, $booking_info['dates']['end']);
        //$enddate = DateTime::createFromFormat(DATE_W3C, $booking_info['dates']['end']);
        $body[] = "End Date: " . $enddate->format("l, F j, Y g:i a");
      }
      $body[] = "Reason: " . $form_vals['stanford_r25_booking_reason'];
      $body[] = "Requested by: " . $res_username . " " . $res_usermail;
      if ($estimated_charge > 0) {
        $body[] = "Estimated Fee: $" . $estimated_charge;
      }
      $params = array(
        'body' => $body,
        'subject' => $subject
      );
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'stanford_earth_r25';
      $key = 'r25_operation';
      $to = $mail_list;
      $params['message'] = $body;
      $params['r25_operation'] = $subject;
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = true;
      $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

      if (!empty($room['postprocess_booking']) && !empty($res_usermail)) {
        $stanford_r25_postprocess = [
          'room' => $booking_info['room'],
          'dates' => $booking_info['dates'],
          'mailto' => $res_usermail,
          'event_name' => $booking_info['stanford_r25_booking_reason'],
          'eventid' => $eventid,
          'est_charge' => $estimated_charge
        ];
        $storage['stanford_earth_r25']['stanford_r25_postprocess'] = $stanford_r25_postprocess;
        $form_state->setStorage($storage);
      }
    }
    else {
      // display a message if the booking failed
      $r25_messages[] = new TranslatableMarkup('<span style="color:red";>The system was <strong>unable</strong> to book your room. This may be because of a time conflict with another meeting, or because someone else booked it first or because of problems communicating with 25Live. Please try again.</span>');
      $storage['stanford_earth_r25']['r25_messages'] = $r25_messages;
      $form_state->setStorage($storage);
      $body = array();
      $event_id = 0;
      if (!empty($result['index']['R25:EVENT_ID'][0]) && !empty($result['vals'][$result['index']['R25:EVENT_ID'][0]]['value'])) {
        $event_id = $result['vals'][$result['index']['R25:EVENT_ID'][0]]['value'];
        $body[] = 'failed reservation at: https://25live.collegenet.com/stanford/#details&obj_type=event&obj_id=' . $event_id;
        $r25_service->r25_api_call('delete', $event_id);
      }
    }

  }

}

