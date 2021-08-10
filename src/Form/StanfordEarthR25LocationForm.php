<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Example add and edit forms.
 */
class StanfordEarthR25LocationForm extends EntityForm {

  /**
   * Constructs an RoomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $location = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $location->label(),
      '#description' => $this->t("Label for the Location."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $location->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$location->isNew(),
    ];

    // You will need additional form elements for your custom properties.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $location = $this->entity;
    $status = $location->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('%Location label created.', [
        '%label' => $location->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Location %label updated.', [
        '%label' => $location->label(),
      ]));
    }

    $form_state->setRedirect('entity.stanford_earth_r25_location.collection');
  }

  /**
   * Helper function to check whether a Location configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('stanford_earth_r25_location')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
