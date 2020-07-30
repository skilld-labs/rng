<?php

namespace Drupal\rng\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\rng\EventManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\courier\Entity\CourierContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormMode;

/**
 * Defines the event type entity.
 *
 * @ConfigEntityType(
 *   id = "rng_event_type",
 *   label = @Translation("Event type"),
 *   handlers = {
 *     "list_builder" = "\Drupal\rng\Lists\EventTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\rng\Form\EventTypeForm",
 *       "edit" = "Drupal\rng\Form\EventTypeForm",
 *       "delete" = "Drupal\rng\Form\EventTypeDeleteForm",
 *       "event_access_defaults" = "Drupal\rng\Form\EventTypeAccessDefaultsForm",
 *       "event_default_messages" = "Drupal\rng\Form\EventTypeDefaultMessagesListForm",
 *       "field_mapping" = "Drupal\rng\Form\EventTypeFieldMappingForm",
 *     }
 *   },
 *   admin_permission = "administer event types",
 *   config_prefix = "rng_event_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/rng/event_types/manage/{rng_event_type}/edit",
 *     "delete-form" = "/admin/structure/rng/event_types/manage/{rng_event_type}/delete",
 *     "event-access-defaults" = "/admin/structure/rng/event_types/manage/{rng_event_type}/access_defaults",
 *     "field-mapping" = "/admin/structure/rng/event_types/manage/{rng_event_type}/field_mapping",
 *   },
 *   config_export = {
 *     "id",
 *     "entity_type",
 *     "bundle",
 *     "mirror_operation_to_event_manage",
 *     "custom_rules",
 *     "default_registrant",
 *     "allow_anon_registrants",
 *     "auto_sync_registrants",
 *     "auto_attach_users",
 *     "registrant_email_field",
 *     "event_start_date_field",
 *     "event_end_date_field",
 *     "people_types",
 *     "default_messages",
 *     "fields",
 *   }
 * )
 */
class RngEventType extends ConfigEntityBase implements EventTypeInterface {

  /**
   * The machine name of this event config.
   *
   * Inspired by two part-ID's from \Drupal\field\Entity\FieldStorageConfig.
   *
   * Config will compute to rng.event.{entity_type}.{bundle}
   *
   * entity_type and bundle are duplicated in file name and config.
   *
   * @var string
   */
  protected $id;

  /**
   * The ID of the event entity type.
   *
   * Matches entities with this entity type.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The ID of the event bundle type.
   *
   * Matches entities with this bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Mirror update permissions.
   *
   * The operation to mirror from the parent entity. For example, if the user
   * has permission to do 'update' operation on the event entity and you want
   * to mirror it. You should set this to 'update'.
   *
   * @var string
   */
  public $mirror_operation_to_event_manage;

  /**
   * Allow event managers to customize default rules.
   *
   * @var bool
   */
  public $custom_rules = TRUE;

  /**
   * Registrant type for new registrants associated with this event type.
   *
   * @var string
   */
  protected $default_registrant;

  /**
   * Whether or not registrants should be allowed to be added registrations
   * without any other identity entity.
   *
   * @var bool
   */
  protected $allow_anon_registrants;

  /**
   * Whether or not matching field data should be sync'd with identities when
   * a registrant is saved.
   *
   * @var bool
   */
  protected $auto_sync_registrants;

  /**
   * Whether or not to automatically attach registrants to user identities by
   * email.
   *
   * @var bool
   */
  protected $auto_attach_users;

  /**
   * An email field on the registrant to use to sync to users.
   *
   * @var string
   */
  protected $registrant_email_field;

  protected $event_start_date_field;

  protected $event_end_date_field;
  /**
   * Types of people types allowed to be associated with this event type.
   *
   * @var array
   */
  protected $people_types = [];

  /**
   * Default messages configured for this event type.
   *
   * @var array
   */
  protected $default_messages = [];

  /**
   * Fields to add to event bundles.
   *
   * @var array
   */
  public $fields = [
    EventManagerInterface::FIELD_REGISTRATION_TYPE,
    EventManagerInterface::FIELD_REGISTRATION_GROUPS,
    EventManagerInterface::FIELD_STATUS,
    EventManagerInterface::FIELD_WAIT_LIST,
    EventManagerInterface::FIELD_REGISTRANTS_CAPACITY,
    EventManagerInterface::FIELD_CAPACITY_CONFIRMED_ONLY,
    EventManagerInterface::FIELD_EMAIL_REPLY_TO,
    EventManagerInterface::FIELD_ALLOW_DUPLICATE_REGISTRANTS,
  ];

  /**
   * {@inheritdoc}
   */
  public function getEventEntityTypeId() {
    return $this->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setEventEntityTypeId($entity_type) {
    $this->entity_type = $entity_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventBundle() {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setEventBundle($bundle) {
    $this->bundle = $bundle;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventManageOperation() {
    return $this->mirror_operation_to_event_manage;
  }

  /**
   * {@inheritdoc}
   */
  public function setEventManageOperation($permission) {
    $this->mirror_operation_to_event_manage = $permission;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowCustomRules() {
    return $this->custom_rules;
  }

  /**
   * {@inheritdoc}
   */
  public function setAllowCustomRules($allow) {
    $this->custom_rules = $allow;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRegistrantType() {
    return $this->default_registrant;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultMessages() {
    return $this->default_messages;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultMessages($messages) {
    $this->default_messages = $messages;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function canIdentityTypeCreate($entity_type, $bundle) {
    $key = $this->getIdentityTypeKey($entity_type, $bundle);
    return !empty($this->people_types[$key]['create']);
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentityTypeCreate($entity_type, $bundle, $enabled) {
    $key = $this->getIdentityTypeKey($entity_type, $bundle);
    $this->people_types[$key]['create'] = $enabled;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentityTypeEntityFormMode($entity_type, $bundle) {
    $key = $this->getIdentityTypeKey($entity_type, $bundle);
    return !empty($this->people_types[$key]['entity_form_mode']) ? $this->people_types[$key]['entity_form_mode'] : 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentityTypeEntityFormModes() {
    $result = [];
    foreach ($this->people_types as $people_type) {
      $required_keys = ['entity_type', 'bundle', 'entity_form_mode'];
      // Ensure keys exist.
      if (count($required_keys) === count(array_intersect_key(array_flip($required_keys), $people_type))) {
        $entity_type = $people_type['entity_type'];
        $bundle = $people_type['bundle'];
        $result[$entity_type][$bundle] = $people_type['entity_form_mode'];
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentityTypeEntityFormMode($entity_type, $bundle, $form_mode) {
    $key = $this->getIdentityTypeKey($entity_type, $bundle);
    $this->people_types[$key]['entity_form_mode'] = $form_mode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function canIdentityTypeReference($entity_type, $bundle) {
    $key = $this->getIdentityTypeKey($entity_type, $bundle);
    return !empty($this->people_types[$key]['existing']);
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentityTypeReference($entity_type, $bundle, $enabled) {
    $key = $this->getIdentityTypeKey($entity_type, $bundle);
    $this->people_types[$key]['existing'] = $enabled;
    return $this;
  }

  /**
   * Get the array key of the people type.
   *
   * @param string $entity_type
   *   The identity entity type ID.
   * @param string $bundle
   *   The identity bundle.
   * @param bool $create_key
   *   Will initialise the array key.
   *
   * @return int|false
   *   The array key, or FALSE if it does not exist.
   */
  protected function getIdentityTypeKey($entity_type, $bundle, $create_key = TRUE) {
    if (isset($this->people_types)) {
      $pairs = $this->people_types;
      foreach ($pairs as $k => $pair) {
        if ($pair['entity_type'] == $entity_type && $pair['bundle'] == $bundle) {
          return $k;
        }
      }
    }

    if ($create_key) {
      // Create if it doesn't exist.
      $next_key = count($this->people_types) + 1;
      $this->people_types[$next_key] = [
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ];
      return $next_key;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultRegistrantType($registrant_type_id) {
    $this->default_registrant = $registrant_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getEventEntityTypeId() . '.' . $this->getEventBundle();
  }

  /**
   * {@inheritdoc}
   */
  public static function courierContextCC($entity_type, $operation) {
    $event_types = \Drupal::service('rng.event_manager')
      ->eventTypeWithEntityType($entity_type);

    if (!count($event_types)) {
      $courier_context = CourierContext::load('rng_registration_' . $entity_type);
      if ($courier_context) {
        if ($operation == 'delete') {
          $courier_context->delete();
        }
      }
      else {
        if ($operation == 'create') {
          $entity_type_info = \Drupal::entityTypeManager()
            ->getDefinition($entity_type);
          $courier_context = CourierContext::create([
            'label' => t('Event (@entity_type): Registration', ['@entity_type' => $entity_type_info->getLabel()]),
            'id' => 'rng_registration_' . $entity_type,
            'tokens' => [$entity_type, 'registration'],
          ]);
          $courier_context->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->isNew()) {
      $this->courierContextCC($this->entity_type, 'create');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Create mode for the entity type.
    $mode_id = $this->entity_type . '.rng_event';
    if (!EntityFormMode::load($mode_id)) {
      EntityFormMode::create([
        'id' => $mode_id,
        'targetEntityType' => $this->entity_type,
        'label' => 'Event Settings',
        'status' => TRUE,
      ])->save();
    }

    if (!$update) {
      module_load_include('inc', 'rng', 'rng.field.defaults');
      foreach ($this->fields as $field) {
        rng_add_event_field_storage($field, $this->entity_type);
        rng_add_event_field_config($field, $this->getEventEntityTypeId(), $this->getEventBundle());
      }
    }

    $display = \Drupal::service('entity_display.repository')->getFormDisplay($this->entity_type, $this->bundle, 'rng_event');
    if ($display->isNew()) {
      // EntityDisplayBase::init() adds default fields. Remove them.
      foreach (array_keys($display->getComponents()) as $name) {
        if (!in_array($name, $this->fields)) {
          $display->removeComponent($name);
        }
      }

      // Weight is the key.
      $field_weights = [
        EventManagerInterface::FIELD_STATUS,
        EventManagerInterface::FIELD_ALLOW_DUPLICATE_REGISTRANTS,
        EventManagerInterface::FIELD_WAIT_LIST,
        EventManagerInterface::FIELD_REGISTRANTS_CAPACITY,
        EventManagerInterface::FIELD_EMAIL_REPLY_TO,
        EventManagerInterface::FIELD_REGISTRATION_TYPE,
        EventManagerInterface::FIELD_REGISTRATION_GROUPS,
      ];

      module_load_include('inc', 'rng', 'rng.field.defaults');
      foreach ($this->fields as $name) {
        rng_add_event_form_display_defaults($display, $name);
        if (in_array($name, $field_weights)) {
          $component = $display->getComponent($name);
          $component['weight'] = array_search($name, $field_weights);
          $display->setComponent($name, $component);
        }
      }

      $display->save();
    }

    // Rebuild routes and local tasks.
    \Drupal::service('router.builder')->setRebuildNeeded();
    // Rebuild local actions https://github.com/dpi/rng/issues/18
    \Drupal::service('plugin.manager.menu.local_action')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    foreach ($this->fields as $field) {
      $field = FieldConfig::loadByName($this->getEventEntityTypeId(), $this->getEventBundle(), $field);
      if ($field) {
        $field->delete();
      }

      $display = \Drupal::service('entity_display.repository')->getFormDisplay($this->entity_type, $this->bundle, 'rng_event');
      if (!$display->isNew()) {
        $display->delete();
      }
    }
    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    if ($event_type = reset($entities)) {
      RngEventType::courierContextCC($event_type->entity_type, 'delete');
    }

    // Rebuild routes and local tasks.
    \Drupal::service('router.builder')->setRebuildNeeded();
    // Rebuild local actions https://github.com/dpi/rng/issues/18
    \Drupal::service('plugin.manager.menu.local_action')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Event entity type/bundle.
    $entity_type = \Drupal::entityTypeManager()
      ->getDefinition($this->getEventEntityTypeId());
    if ($entity_type) {
      $bundle_entity_type = $entity_type->getBundleEntityType();
      if ($bundle_entity_type && $bundle_entity_type !== 'bundle') {
        $bundle = \Drupal::entityTypeManager()
          ->getStorage($entity_type->getBundleEntityType())
          ->load($this->getEventBundle());
        if ($bundle) {
          $this->addDependency('config', $bundle->getConfigDependencyName());
        }
      }
      else {
        $this->addDependency('module', $entity_type->getProvider());
      }
    }

    // Default registrant type.
    $registrant_type_id = $this->getDefaultRegistrantType();
    if ($registrant_type_id) {
      $registrant_type = RegistrantType::load($registrant_type_id);
      if ($registrant_type) {
        $this->addDependency('config', $registrant_type->getConfigDependencyName());
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    foreach ($dependencies['config'] as $entity) {
      if ($entity instanceof RegistrantTypeInterface) {
        // Registrant type is being deleted.
        if ($entity->id() === $this->getDefaultRegistrantType()) {
          $this->setDefaultRegistrantType(NULL);
          $changed = TRUE;
        }
      }
    }

    return $changed;
  }

  /**
   * @inheritDoc
   */
  public function getAllowAnonRegistrants() {
    return $this->allow_anon_registrants;
  }

  /**
   * @inheritDoc
   */
  public function setAllowAnonRegistrants($allow_anon_registrants) {
    $this->allow_anon_registrants = $allow_anon_registrants;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getAutoSyncRegistrants() {
    return $this->auto_sync_registrants;
  }

  /**
   * @inheritDoc
   */
  public function setAutoSyncRegistrants($auto_sync_registrants) {
    $this->auto_sync_registrants = $auto_sync_registrants;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getAutoAttachUsers() {
    return $this->auto_attach_users;
  }

  /**
   * @inheritDoc
   */
  public function setAutoAttachUsers($auto_attach_users) {
    $this->auto_attach_users = $auto_attach_users;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getRegistrantEmailField() {
    return $this->registrant_email_field;
  }

  /**
   * @inheritDoc
   */
  public function setRegistrantEmailField($registrant_email_field) {
    $this->registrant_email_field = $registrant_email_field;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getEventStartDateField() {
    return $this->event_start_date_field;
  }

  /**
   * @inheritDoc
   */
  public function setEventStartDateField($event_start_date_field) {
    $this->event_start_date_field = $event_start_date_field;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getEventEndDateField() {
    return $this->event_end_date_field;
  }

  /**
   * @inheritDoc
   */
  public function setEventEndDateField($event_end_date_field) {
    $this->event_end_date_field = $event_end_date_field;
    return $this;
  }
}
