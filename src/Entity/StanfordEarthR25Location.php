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
 *     "event_attributes",
 *     "event_attributes_field",
 *     "contact_attribute",
 *     "contact_attribute_field",
 *     "auto_billing_code",
 *     "location_info"
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
  protected $event_attributes_field;

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
   * Location info from R25.
   *
   * @var array
   */
  protected $location_info;

  /**
   * {@inheritdoc}
   */
  public function save() {
    $location_info = StanfordEarthR25Util::stanfordR25GetRoomInfo($this->get('space_id'));
    $this->set('updated', \Drupal::service('date.formatter')->format(time()));
    $this->set('location_info', $location_info);
    $return = parent::save();
    return $return;
  }

}
