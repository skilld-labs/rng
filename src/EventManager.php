<?php

namespace Drupal\rng;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rng\Entity\EventTypeInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\rng\Exception\InvalidEventException;
use Drupal\Core\Cache\Cache;

/**
 * Event manager for RNG.
 */
class EventManager implements EventManagerInterface {

  use ContainerAwareTrait;

  /**
   * An array of event meta instances.
   *
   * @var \Drupal\rng\EventMeta[]
   */
  protected $event_meta = [];

  /**
   * Event type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $eventTypeStorage;

  /**
   * Constructs a new EventManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->eventTypeStorage = $entity_type_manager->getStorage('rng_event_type');
  }

  /**
   * {@inheritdoc}
   */
  public function getMeta(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $id = $entity->id();

    if (!$this->isEvent($entity)) {
      throw new InvalidEventException(sprintf('%s: %s is not an event bundle.', $entity->getEntityTypeId(), $entity->bundle()));
    }

    if (!isset($this->event_meta[$entity_type][$id])) {
      $this->event_meta[$entity_type][$id] = EventMeta::createInstance($this->container, $entity);
    }

    return $this->event_meta[$entity_type][$id];
  }

  /**
   * {@inheritdoc}
   */
  public function isEvent(EntityInterface $entity) {
    return (boolean) $this->eventType($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function eventType($entity_type, $bundle) {
    $ids = $this->eventTypeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $entity_type, '=')
      ->condition('bundle', $bundle, '=')
      ->execute();

    if ($ids) {
      return $this->eventTypeStorage
        ->load(reset($ids));
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function eventTypeWithEntityType($entity_type) {
    $ids = $this->eventTypeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $entity_type, '=')
      ->execute();

    if ($ids) {
      return $this->eventTypeStorage->loadMultiple($ids);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEventTypes() {
    /** @var \Drupal\rng\Entity\EventTypeInterface[] $event_types */
    $entity_type_bundles = [];
    foreach ($this->eventTypeStorage->loadMultiple() as $entity) {
      $entity_type_bundles[$entity->getEventEntityTypeId()][$entity->getEventBundle()] = $entity;
    }
    return $entity_type_bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateEventTypes() {
    $event_types = $this->getEventTypes();
    foreach ($event_types as $i => $bundles) {
      foreach ($bundles as $b => $event_type) {
        /** @var \Drupal\rng\Entity\EventTypeInterface $event_type */
        $this->invalidateEventType($event_type);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateEventType(EventTypeInterface $event_type) {
    Cache::invalidateTags($event_type->getCacheTags());
  }

}
