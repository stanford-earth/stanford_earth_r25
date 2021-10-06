<?php

namespace Drupal\stanford_earth_r25\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

/**
 * Provides a room reservation page.
 */
class StanfordEarthR25ReservationController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Entity type manager to load room info.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Current user for checking permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The StanfordEarthR25ReservationController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The Drupal entity type manager to load room entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current Drupal user account.
   */
  public function __construct(FormBuilder $formBuilder,
                              EntityTypeManager $entityTypeManager,
                              AccountInterface $account) {
    $this->formBuilder = $formBuilder;
    $this->entityTypeManager = $entityTypeManager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Returns a calendar page render array.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Reservation form ajax modal dialog.
   */
  public function reserve($location_id, $start) {
    $response = new AjaxResponse();
    // Make sure the current user has permission to book the room.
    $entity = $this->entityTypeManager->getStorage('stanford_earth_r25_location')
      ->load($location_id);
    if (StanfordEarthR25Util::stanfordR25CanBookRoom($entity, $this->account)) {
      // Get the modal form using the form builder.
      $modal_form =
        $this->formBuilder->getForm('Drupal\stanford_earth_r25\Form\StanfordEarthR25ReservationForm',
          $location_id, $start);
      // Add an AJAX command to open a modal dialog with form as the content.
      $response->addCommand(new OpenModalDialogCommand('Room Reservation Form',
        $modal_form, ['width' => '800']));
    }
    else {
      $response->addCommand(new OpenModalDialogCommand('Room Reservation Form',
        'User does not have permission to book this location.', ['width' => '800']));
    }
    return $response;
  }

}
