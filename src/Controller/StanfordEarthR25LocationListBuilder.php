<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Stanford Earth R25 Locations.
 */
class StanfordEarthR25LocationListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Location');
    $header['id'] = $this->t('ID');
    $header['caltype'] = $this->t('Cal Type');
    $header['space_id'] = $this->t('Space ID');
    $header['displaytype'] = $this->t('Res Type');
    $header['approver_secgroup_name'] = $this->t('Security Group');
    $header['updated'] = $this->t('Last Updated');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $caltype = $entity->get('caltype');
    if ($caltype == 1) {
      $caltype = '25Live Publisher';
    } else {
      $caltype = 'Fullcalendar';
    }
    $row['caltype'] = $caltype;
    $row['space_id'] = $entity->get('space_id');
    $displayType = $entity->get('displaytype');
    if ($displayType == 0) {
      $displayType = 'Disabled';
    } else if ($displayType == 1) {
      $displayType = 'Read-Only Calendar';
    } else if ($displayType == 2) {
      $displayType = 'Tentative Bookings';
    } else if ($displayType == 3) {
      $displayType = 'Confirmed Bookings';
    } else{
      $displayType = 'Unknown';
    }
    $row['displaytype'] = $displayType;
    $row['approver_secgroup_name'] = $entity->get('approver_secgroup_name');
    $row['updated'] = $entity->get('updated');
    /*
    $header['space_id'] = $this->t('25Live Space ID');
    $header['displaytype'] = $this->('Reservation Type');
    $header['updated'] = $this->('Last updated');
*/
    // You probably want a few more properties here...

    return $row + parent::buildRow($entity);
  }

}
