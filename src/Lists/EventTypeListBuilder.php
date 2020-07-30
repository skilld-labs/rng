<?php

namespace Drupal\rng\Lists;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Builds a list of event config entities.
 */
class EventTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $entity_type_manager = $container->get('entity_type.manager');
    $instance = new static(
      $entity_type,
      $entity_type_manager->getStorage($entity_type->id())
    );
    $instance->entityTypeManager = $entity_type_manager;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\rng\Entity\EventTypeInterface $entity **/
    $operations = parent::getDefaultOperations($entity);

    if ($this->moduleHandler->moduleExists('field_ui')) {
      $entity_type = $this->entityTypeManager
        ->getDefinition($entity->getEventEntityTypeId());

      if ($entity_type->get('field_ui_base_route')) {
        $options = [];
        if ($entity_type->getBundleEntityType() !== 'bundle') {
          $options[$entity_type->getBundleEntityType()] = $entity->getEventBundle();
        }
        $operations['manage-fields'] = [
          'title' => t('Event setting defaults'),
          'weight' => 15,
          'url' => Url::fromRoute("entity." . $entity->getEventEntityTypeId() . ".field_ui_fields", $options),
        ];
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['machine_name'] = $this->t('Event type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\rng\Entity\EventTypeInterface $entity **/

    $entity_type = $this->entityTypeManager
      ->getDefinition($entity->getEventEntityTypeId());
    $t_args = ['@entity_type' => $entity_type->getLabel()];
    $bundle_entity_type = $entity_type->getBundleEntityType();
    if ($bundle_entity_type && $bundle_entity_type !== 'bundle') {
      $bundle = $this->entityTypeManager
        ->getStorage($bundle_entity_type)
        ->load($entity->getEventBundle());
      $t_args['@bundle'] = $bundle->label();
      $row['machine_name'] = $this->t('@entity_type: @bundle', $t_args);
    }
    else {
      // Entity type does not use bundles.
      $row['machine_name'] = $this->t('@entity_type', $t_args);
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $render = parent::render();
    $render['table']['#empty'] = t('No event types found.');
    return $render;
  }

}
