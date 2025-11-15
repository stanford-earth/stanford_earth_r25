<?php

namespace Drupal\stanford_earth_r25\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

/**
 * Defines the Stanford Earth R25 Location entity.
 *
 * @ConfigEntityType(
 *   id = "stanford_earth_r25_location",
 *   label = @Translation("Stanford Earth R25 Location"),
 *   handlers = {
 *     "list_builder" =
 *   "Drupal\stanford_earth_r25\Controller\StanfordEarthR25LocationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\stanford_earth_r25\Form\StanfordEarthR25LocationForm",
 *       "edit" =
 *   "Drupal\stanford_earth_r25\Form\StanfordEarthR25LocationForm",
 *       "delete" =
 *   "Drupal\stanford_earth_r25\Form\StanfordEarthR25LocationDeleteForm",
 *     }
 *   },
 *   config_prefix = "stanford_earth_r25",
 *   admin_permission = "administer stanford r25",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "spud_name",
 *     "space_id",
 *     "email_list",
 *     "updated",
 *     "displaytype",
 *     "caltype",
 *     "max_hours",
 *     "default_view",
 *     "description_as_title",
 *     "permalink",
 *     "honor_blackouts",
 *     "override_blackout_instructions",
 *     "approver_secgroup_name",
 *     "approver_secgroup_id",
 *     "email_cancellations",
 *     "multi_day",
 *     "postprocess_booking",
 *     "override_booking_instructions",
 *     "legend_labels",
 *     "event_attributes",
 *     "event_attributes_fields",
 *     "contact_attribute",
 *     "contact_attribute_field",
 *     "auto_billing_code",
 *     "override_view_roles",
 *     "override_book_roles",
 *     "nopopup_reservation_form",
 *     "location_info",
 *     "locationtype",
 *     "hide_titles_for_non_managers",
 *     "override_organization_id",
 *     "override_event_code",
 *     "override_event_name",
 *     "allowed_timeslots_fields",
 *     "allowed_timeslots",
 *     "allowed_dates_fields",
 *     "allowed_dates",
 *     "override_room_description",
 *     "room_administrator_roles",
 *     "room_administrator_emails",
 *     "rules_category",
 *     "future_days",
 *     "abbreviations",
 *     "slot_min_time",
 *     "slot_max_time",
 *     "hide_weekends",
 *     "multi_room_capacities",
 *     "multi_room_parents",
 *     "use_admin_email_instead",
 *     "earliest_day",
 *     "email_only_request",
 *     "extra_hours",
 *     "extra_hours_roles",
 *     "post_process_redirect_url",
 *   },
 *   links = {
 *     "edit-form" =
 *   "/admin/config/system/stanford_earth_r25/{stanford_earth_r25_location}",
 *     "delete-form" =
 *   "/admin/config/system/stanford_earth_r25/{stanford_earth_r25_location}/delete",
 *   }
 * )
 */
class StanfordEarthR25Location extends ConfigEntityBase implements StanfordEarthR25LocationInterface {

  /**
   * The Location ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Location label.
   *
   * @var string
   */
  protected $label;

  /**
   * The R25 Spud name.
   *
   * @var string
   */
  protected $spud_name;

  /**
   * The R25 Location id.
   *
   * @var string
   */
  protected $space_id;

  /**
   * The notification email list.
   *
   * @var string
   */
  protected $email_list;

  /**
   * The Location create date/time.
   *
   * @var string
   */
  protected $updated;

  /**
   * Location displaytype.
   *
   * @var int
   */
  protected $displaytype;

  /**
   * The Calendar display type.
   *
   * @var int
   */
  protected $caltype;

  /**
   * Max reservation hours.
   *
   * @var int
   */
  protected $max_hours;

  /**
   * Default calendar view.
   *
   * @var int
   */
  protected $default_view;

  /**
   * Use event descriptio as title.
   *
   * @var bool
   */
  protected $description_as_title;

  /**
   * Display permalink on calendar page.
   *
   * @var bool
   */
  protected $permalink;

  /**
   * Honor blackout dates.
   *
   * @var bool
   */
  protected $honor_blackouts;

  /**
   * Override blackout instructions.
   *
   * @var string
   */
  protected $override_blackout_instructions;

  /**
   * Approver Security Group Name.
   *
   * @var string
   */
  protected $approver_secgroup_name;

  /**
   * Approver security group id.
   *
   * @var int
   */
  protected $approver_secgroup_id;

  /**
   * Email cancellations to approvers.
   *
   * @var bool
   */
  protected $email_cancellations;

  /**
   * Allow multi-day reservations.
   *
   * @var bool
   */
  protected $multi_day;

  /**
   * Postprocess bookings for this location.
   *
   * @var bool
   */
  protected $postprocess_booking;


  /**
   * Override booking instructions.
   *
   * @var string
   */
  protected $override_booking_instructions;

  /**
   * Room labels for multi-room legend.
   *
   * @var string
   */
  protected $legend_labels;

  /**
   * Event attributes.
   *
   * @var string
   */
  protected $event_attributes;

  /**
   * Event Attributes R25 field.
   *
   * @var array
   */
  protected $event_attributes_fields;

  /**
   * Contact attribute.
   *
   * @var string
   */
  protected $contact_attribute;

  /**
   * Contact Attribute R25 field.
   *
   * @var array
   */
  protected $contact_attribute_field;

  /**
   * Auto billing code.
   *
   * @var string
   */
  protected $auto_billing_code;

  /**
   * Override View Roles.
   *
   * @var array
   */
  protected $override_view_roles;

  /**
   * Override Book Roles.
   *
   * @var array
   */
  protected $override_book_roles;

  /**
   * No Pop-up reservation form for location.
   *
   * @var bool
   */
  protected $nopopup_reservation_form;

  /**
   * Location info from R25.
   *
   * @var array
   */
  protected $location_info;

  /**
   * Location type.
   *
   * @var int
   */
  protected $locationtype;

  /**
   * Hide event titles for non-authorized users.
   *
   * @var bool
   */
  protected $hide_titles_for_non_managers;

  /**
   * Override Organization ID.
   *
   * @var string
   */
  protected $override_organization_id;

  /**
   * Override Event Code.
   *
   * @var string
   */
  protected $override_event_code;

  /**
   * Override Event Name.
   *
   * @var string
   */
  protected $override_event_name;

  /**
   * Allowed dates input string.
   *
   * @var string
   */
  protected $allowed_dates;

  /**
   * Allowed booking dates array.
   *
   * @var array
   */
  protected $allowed_dates_fields;

  /**
   * Allowed timeslots input string.
   *
   * @var string
   */
  protected $allowed_timeslots;

  /**
   * Allowed timeslots array.
   *
   * @var array
   */

  protected $allowed_timeslots_fields;

  /**
   * Override room description.
   *
   * @var string
   */
  protected $override_room_description;

  /**
   * Room Administrator Roles.
   *
   * @var array
   */
  protected $room_administrator_roles;

  /**
   * Contact email addresses for room administrators..
   *
   * @var string
   */
  protected $room_administrator_emails;

  /**
   * Categorize this room for special processing.
   *
   * @var string
   */
  protected $rules_category;

  /**
   * How far into the future can we book.
   *
   * @var int
   */
  protected $future_days;

  /**
   * Location abbreviations for reservations on multi-room calendars.
   *
   * @var string
   */
  protected $abbreviations;

  /**
   * Minimum timeslot to show on Fullcalendar.
   *
   * @var string
   */
  protected $slot_min_time;

  /**
   * Maximum timeslot to show on Fullcalendar.
   *
   * @var string
   */
  protected $slot_max_time;

  /**
   * Hide weekends in Fullcalendar.
   *
   * @var bool
   */
  protected $hide_weekends;

  /**
   * Capacities for multiple-room calendars from R25.
   *
   * @var array
   */
  protected $multi_room_capacities;

  /**
   * Parent rooms of locations in multi-room calendars..
   *
   * @var array
   */
  protected $multi_room_parents;

  /**
   * Use admin email address instead of secgroup email.
   *
   * @var bool
   */
  protected $use_admin_email_instead;

  /**
   * Earliest number of days from today for first possible booking.
   *
   * @var int
   */
  protected $earliest_day;

  /**
   * String containing subject and body for an email-only booking request.
   *
   * @var string
   */
  protected $email_only_request;

  /**
   * Extra hours to add before slot_min_time and after slot_max_time.
   *
   * @var int
   */
  protected $extra_hours;

  /**
   * Extra hours roles.
   *
   * @var array
   */
  protected $extra_hours_roles;

  /**
   * Post-process redirect url.
   *
   * @var string
   */
  protected $post_process_redirect_url;

  /**
   * {@inheritdoc}
   */
  public function save() {
    $location_info = StanfordEarthR25Util::stanfordR25GetRoomInfo($this->get('space_id'));
    $this->set('updated', \Drupal::service('date.formatter')->format(time()));
    $this->set('location_info', $location_info);
    $event_attributes_fields =
      StanfordEarthR25Util::stanfordR25UpdateEventAttributeFields($this->get('event_attributes'));
    $this->set('event_attributes_fields', $event_attributes_fields);
    $contact_attribute_field =
      StanfordEarthR25Util::stanfordR25UpdateEventAttributeFields($this->get('contact_attribute'));
    $this->set('contact_attribute_field', $contact_attribute_field);
    $allowed_dates_fields =
      StanfordEarthR25Util::stanfordR25ParseBlackoutDates($this->get('allowed_dates'));
    $this->set('allowed_dates_fields', $allowed_dates_fields);
    $allowed_timeslots_fields =
      StanfordEarthR25Util::stanfordR25ParseTimeslots($this->get('allowed_timeslots'));
    $this->set('allowed_timeslots_fields', $allowed_timeslots_fields);
    // If multi-room calendar, get capacities and parents for each room.
    $capacities = [];
    $parents = [];
    $space_id = $this->get('space_id');
    if (strpos($space_id, "+") !== FALSE) {
      $locations = explode("+", $space_id);
      foreach ($locations as $location) {
        $location_data = StanfordEarthR25Util::stanfordR25GetRoomInfo($location);
        $capacity = 5;
        if (!empty($location_data['capacity'])) {
          $capacity = $location_data['capacity'];
        }
        $capacities[$location] = $capacity;
        if (!empty($location_data['parents'])) {
          $parents[$location] = $location_data['parents'];
        }
      }
      $this->set('multi_room_capacities', $capacities);
      $this->set('multi_room_parents', $parents);
    }
    $return = parent::save();

    return $return;
  }

}
