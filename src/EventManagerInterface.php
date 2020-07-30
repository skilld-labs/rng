<?php

namespace Drupal\rng;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rng\Entity\EventTypeInterface;

/**
 * Event manager for RNG.
 */
interface EventManagerInterface {

  /**
   * ID of an `entity_reference` field attached to an event bundle.
   *
   * Specifies the registration type of registrations that can be created for
   * an event. This field references registration_type entities.
   */
  const FIELD_REGISTRATION_TYPE = 'rng_registration_type';

  /**
   * ID of an `entity_reference` field attached to an event bundle.
   *
   * Specifies the groups that are applied to new registrations. This field
   * references registration_group entities.
   */
  const FIELD_REGISTRATION_GROUPS = 'rng_registration_groups';

  /**
   * ID of an `boolean` field attached to an event bundle.
   *
   * Whether an event is accepting new registrations.
   */
  const FIELD_STATUS = 'rng_status';

  /**
   * ID of an `integer` field attached to an event bundle.
   *
   * The absolute maximum number of registrants that can be signed up
   * for an event. A negative or missing value indicates unlimited capacity.
   */
  const FIELD_REGISTRANTS_CAPACITY = 'rng_registrants_capacity';

  /**
   * ID of a 'boolean' field attached to an event bundle.
   *
   * Whether FIELD_REGISTRANTS_CAPACITY include
   * confirmed registrations only (TRUE) or all registrations, including
   * unconfirmed (FALSE).
   */
  const FIELD_CAPACITY_CONFIRMED_ONLY = 'rng_capacity_confirmed_only';

  /**
   * ID of an `boolean` field attached to an event bundle.
   *
   * Whether an event allows a wait list.
   */
  const FIELD_WAIT_LIST = 'rng_wait_list';

  /**
   * ID of an `email` field attached to an event bundle.
   *
   * Reply-to address for e-mails sent from an event.
   */
  const FIELD_EMAIL_REPLY_TO = 'rng_reply_to';

  /**
   * ID of an `boolean` field attached to an event bundle.
   *
   * Whether an event allows a registrant to associate with multiple
   * registrations. An empty value reverts to the site default.
   */
  const FIELD_ALLOW_DUPLICATE_REGISTRANTS = 'rng_registrants_duplicate';

  /**
   * Get the meta instance for an event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An event entity.
   *
   * @return \Drupal\rng\EventMetaInterface|null
   *   An event meta object.
   *
   * @throws \Drupal\rng\Exception\InvalidEventException
   *   If the $entity is not an event.
   */
  public function getMeta(EntityInterface $entity);

  /**
   * Determines if an entity is an event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An event entity.
   *
   * @return bool
   *   Whether the entity is an event.
   */
  public function isEvent(EntityInterface $entity);

  /**
   * Get event type config for an event bundle.
   *
   * Use this to test whether an entity bundle is an event type.
   *
   * @param string $entity_type
   *   An entity type ID.
   * @param string $bundle
   *   A bundle ID.
   *
   * @return \Drupal\rng\Entity\EventTypeInterface|null
   */
  public function eventType($entity_type, $bundle);

  /**
   * Gets all event types associated with an entity type.
   *
   * @param string $entity_type
   *   An entity type ID.
   *
   * @return \Drupal\rng\Entity\EventTypeInterface[]
   *   An array of event type config entities
   */
  public function eventTypeWithEntityType($entity_type);

  /**
   * Get all event types configuration entities.
   *
   * @return array
   *   A multidimensional array: [event_entity_type][event_bundle] = $event_type
   */
  public function getEventTypes();

  /**
   * Invalidate cache for events types.
   */
  public function invalidateEventTypes();

  /**
   * Invalidate cache for an event type.
   *
   * @param \Drupal\rng\Entity\EventTypeInterface $event_type
   *   An event type.
   */
  public function invalidateEventType(EventTypeInterface $event_type);

}
