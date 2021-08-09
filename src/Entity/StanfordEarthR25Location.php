namespace Drupal\stanford_earth_r25\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\stanford_earth_r25\StanfordEarthR25LocationInterface;

/**
* Defines the Stanford Earth R25 Location entity.
*
* @ConfigEntityType(
*   id = "stanford_earth_r25_location",
*   label = @Translation("Stanford Earth R25 Location"),
*   handlers = {
*     "list_builder" = "Drupal\stanford_earth_r25\Controller\StanfordEarthR25LocationListBuilder",
*     "form" = {
*       "add" = "Drupal\stanford_earth_r25\Form\StanfordEarthR25LocationForm",
*       "edit" = "Drupal\stanford_earth_r25\Form\StanfordEarthR25LocationForm",
*       "delete" = "Drupal\stanford_earth_r25\Form\StanfordEarthR25LocationDeleteForm",
*     }
*   },
*   config_prefix = "stanford_earth_r25_location",
*   admin_permission = "administer site configuration",
*   entity_keys = {
*     "id" = "id",
*     "label" = "label",
*   },
*   config_export = {
*     "id",
*     "label"
*   },
*   links = {
*     "edit-form" = "/admin/config/system/stanford_earth_r25/{stanford_earth_r25_location}",
*     "delete-form" = "/admin/config/system/stanford_earth_r25/{stanford_earth_r25_location}/delete",
*   }
* )
*/
class StanfordEarthR25Location extends ConfigEntityBase
  implements StanfordEarthR25LocationInterface {

/**
* The Room ID.
*
* @var string
*/
protected $id;

/**
* The Room label.
*
* @var string
*/
protected $label;

// Your specific configuration property get/set methods go here,
// implementing the interface.
}
