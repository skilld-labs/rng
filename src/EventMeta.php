<?php

namespace Drupal\rng;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\rng\Entity\RegistrationTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\courier\Service\IdentityChannelManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\rng\Entity\Rule;
use Drupal\rng\Entity\RuleComponent;
use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\Service\CourierManagerInterface;
use Drupal\Core\Action\ActionManager;

/**
 * Meta event wrapper for RNG.
 */
class EventMeta implements EventMetaInterface {

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionPluginManager;

  /**
   * The identity channel manager.
   *
   * @var \Drupal\courier\Service\IdentityChannelManagerInterface
   */
  protected $identityChannelManager;

  /**
   * The RNG configuration service.
   *
   * @var \Drupal\rng\RngConfigurationInterface
   */
  protected $rngConfiguration;

  /**
   * The RNG event manager.
   *
   * @var \Drupal\rng\EventManagerInterface
   */
  protected $eventManager;

  /**
   * The courier manager.
   *
   * @var \Drupal\courier\Service\CourierManagerInterface
   */
  protected $courier_manager;

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $action_manager;

  /**
   * Constructs a new EventMeta object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param EntityTypeBundleInfoInterface $bundle_info
   *   The bundle info.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_plugin_manager
   *   The selection plugin manager.
   * @param \Drupal\courier\Service\IdentityChannelManagerInterface $identity_channel_manager
   *   The identity channel manager.
   * @param \Drupal\rng\RngConfigurationInterface $rng_configuration
   *   The RNG configuration service.
   * @param \Drupal\rng\EventManagerInterface $event_manager
   *   The RNG event manager.
   * @param \Drupal\courier\Service\CourierManagerInterface $courier_manager
   *   The courier manager.
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action manager.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The event entity.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info, ConfigFactoryInterface $config_factory, SelectionPluginManagerInterface $selection_plugin_manager, IdentityChannelManagerInterface $identity_channel_manager, RngConfigurationInterface $rng_configuration, EventManagerInterface $event_manager, CourierManagerInterface $courier_manager, ActionManager $action_manager, EntityInterface $entity) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->configFactory = $config_factory;
    $this->selectionPluginManager = $selection_plugin_manager;
    $this->identityChannelManager = $identity_channel_manager;
    $this->rngConfiguration = $rng_configuration;
    $this->eventManager = $event_manager;
    $this->courier_manager = $courier_manager;
    $this->action_manager = $action_manager;
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityInterface $entity) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('config.factory'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('plugin.manager.identity_channel'),
      $container->get('rng.configuration'),
      $container->get('rng.event_manager'),
      $container->get('courier.manager'),
      $container->get('plugin.manager.action'),
      $entity
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEvent() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventType() {
    return $this->eventManager->eventType($this->entity->getEntityTypeId(), $this->entity->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function isAcceptingRegistrations() {
    return !empty($this->getEvent()->{EventManagerInterface::FIELD_STATUS}->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getReplyTo() {
    return $this->getEvent()->{EventManagerInterface::FIELD_EMAIL_REPLY_TO}->value;
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateRegistrantsAllowed() {
    return !empty($this->getEvent()->{EventManagerInterface::FIELD_ALLOW_DUPLICATE_REGISTRANTS}->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypeIds() {
    return array_map(function ($element) {
      return isset($element['target_id']) ? $element['target_id'] : [];
    }, $this->getEvent()->{EventManagerInterface::FIELD_REGISTRATION_TYPE}->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypes() {
    return $this->entityTypeManager->getStorage('registration_type')->loadMultiple($this->getRegistrationTypeIds());
  }

  /**
   * {@inheritdoc}
   */
  public function registrationTypeIsValid(RegistrationTypeInterface $registration_type) {
    return in_array($registration_type->id(), $this->getRegistrationTypeIds());
  }

  /**
   * {@inheritdoc}
   */
  public function removeRegistrationType($registration_type_id) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $registration_types */
    $registration_types = &$this->entity->{EventManagerInterface::FIELD_REGISTRATION_TYPE};
    foreach ($registration_types->getValue() as $key => $value) {
      if ($value['target_id'] == $registration_type_id) {
        $registration_types->removeItem($key);
      }
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function removeGroup($group_id) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $groups */
    $groups = &$this->entity->{EventManagerInterface::FIELD_REGISTRATION_GROUPS};
    foreach ($groups->getValue() as $key => $value) {
      if ($value['target_id'] == $group_id) {
        $groups->removeItem($key);
      }
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrantCapacity() {
    $capacity = (int) $this->getEvent()->{EventManagerInterface::FIELD_REGISTRANTS_CAPACITY}->value;
    if ($capacity != '' && is_numeric($capacity) && $capacity >= 0) {
      return $capacity;
    }
    return EventMetaInterface::CAPACITY_UNLIMITED;
  }

  /**
   * {@inheritdoc}
   */
  public function remainingRegistrantCapacity() {
    $capacity = $this->getRegistrantCapacity();
    if ($capacity == EventMetaInterface::CAPACITY_UNLIMITED) {
      return $capacity;
    }
    $remaining = $capacity - $this->countRegistrants();
    return $remaining > 0 ? $remaining : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function allowWaitList() {
    return (bool) $this->getEvent()->{EventManagerInterface::FIELD_WAIT_LIST}->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrantsMaximum() {
    if (isset($this->getEvent()->{EventManagerInterface::FIELD_REGISTRANTS_CAPACITY})) {
      $field = $this->getEvent()->{EventManagerInterface::FIELD_REGISTRANTS_CAPACITY};
      $maximum = $field->value;
      if ($maximum !== '' && is_numeric($maximum) && $maximum >= 0) {
        return $maximum;
      }
    }
    return EventMetaInterface::CAPACITY_UNLIMITED;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultGroups() {
    $groups = [];
    foreach ($this->getEvent()->{EventManagerInterface::FIELD_REGISTRATION_GROUPS} as $group) {
      $groups[] = $group->entity;
    }
    return $groups;
  }

  /**
   * {@inheritdoc}
   */
  public function buildQuery($entity_type) {
    return $this->entityTypeManager->getStorage($entity_type)->getQuery('AND')
      ->condition('event__target_type', $this->getEvent()->getEntityTypeId(), '=')
      ->condition('event__target_id', $this->getEvent()->id(), '=');
  }

  /**
   * {@inheritdoc}
   */
  public function buildEventRegistrantQuery() {
    $query = \Drupal::database()->select('registrant', 'ant');
    $query->join('registration', 'ion', 'ion.id = ant.registration');
    $query->join('registration_field_data', 'rfd', 'ion.id = rfd.id');
    $query->fields('ant', ['id']);
    $query->condition('rfd.event__target_type', $this->getEvent()->getEntityTypeId(), '=');
    $query->condition('rfd.event__target_id', $this->getEvent()->id(), '=');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRegistrationQuery() {
    return $this->buildQuery('registration');
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrations() {
    $query = $this->buildRegistrationQuery();
    return $this->entityTypeManager->getStorage('registration')->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function countRegistrants() {
    return $this->buildEventRegistrantQuery()->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function countRegistrations() {
    return $this->buildRegistrationQuery()->count()->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRuleQuery() {
    return $this->buildQuery('rng_rule');
  }

  /**
   * {@inheritdoc}
   */
  public function getRules($trigger = NULL, $defaults = FALSE, $is_active = TRUE) {
    $query = $this->buildRuleQuery();

    if ($trigger) {
      $query->condition('trigger_id', $trigger, '=');
    }

    if (isset($is_active)) {
      $query->condition('status', $is_active, '=');
    }

    $rules = $this->entityTypeManager
      ->getStorage('rng_rule')
      ->loadMultiple($query->execute());
    if ($defaults && !$rules) {
      return $this->getDefaultRules($trigger);
    }

    return $rules;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRules($trigger = NULL) {
    $rules = [];

    if ($trigger != 'rng_event.register') {
      return $rules;
    }

    /** @var \Drupal\rng\Entity\EventTypeRuleInterface[] $default_rules */
    $default_rules = $this
      ->entityTypeManager
      ->getStorage('rng_event_type_rule')
      ->loadByProperties([
        'entity_type' => $this->getEvent()->getEntityTypeId(),
        'bundle' => $this->getEvent()->bundle(),
        'trigger' => $trigger,
      ]);

    foreach ($default_rules as $default_rule) {
      $rule = Rule::create([
        'event' => ['entity' => $this->getEvent()],
        'trigger_id' => $trigger,
        'status' => TRUE,
      ]);

      foreach ($default_rule->getConditions() as $condition) {
        $plugin_id = $condition['id'];
        unset($condition['id']);
        $component = RuleComponent::create()
          ->setType('condition')
          ->setPluginId($plugin_id)
          ->setConfiguration($condition);
        $rule->addComponent($component);
      }

      foreach ($default_rule->getActions() as $action) {
        $component = RuleComponent::create()
          ->setType('action')
          ->setPluginId($action['id'])
          ->setConfiguration($action['configuration']);
        $rule->addComponent($component);
      }

      $rules[] = $rule;
    }

    return $rules;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultRules($trigger) {
    return (boolean) !$this->getRules($trigger);
  }

  /**
   * {@inheritdoc}
   */
  public function trigger($trigger, $context = []) {
    $context['event'] = $this->getEvent();
    foreach ($this->getRules($trigger) as $rule) {
      if ($rule->evaluateConditions()) {
        foreach ($rule->getActions() as $action) {
          $action->execute($context);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildGroupQuery() {
    return $this->buildQuery('registration_group');
  }

  /**
   * {@inheritdoc}
   */
  public function getGroups() {
    $query = $this->buildGroupQuery();
    return $this->entityTypeManager->getStorage('registration_group')->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function buildRegistrantQuery($entity_type_id = NULL) {
    $query = $this->entityTypeManager->getStorage('registrant')->getQuery('AND')
      ->condition('registration.entity.event__target_type', $this->getEvent()->getEntityTypeId(), '=')
      ->condition('registration.entity.event__target_id', $this->getEvent()->id(), '=');

    if ($entity_type_id) {
      $query->condition('identity__target_type', $entity_type_id, '=');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrants($entity_type_id = NULL) {
    $query = $this->buildRegistrantQuery($entity_type_id);
    return $this->entityTypeManager->getStorage('registrant')->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function canRegisterProxyIdentities() {
    // Create is checked first since it is usually the cheapest.
    $identity_types = $this->getCreatableIdentityTypes();
    foreach ($identity_types as $entity_type_id => $bundles) {
      $accessControl = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
      if ($this->entityTypeHasBundles($entity_type_id)) {
        foreach ($bundles as $bundle) {
          if ($accessControl->createAccess($bundle)) {
            return TRUE;
          }
        }
      }
      elseif (!empty($bundles)) {
        if ($accessControl->createAccess()) {
          return TRUE;
        }
      }
    }

    // Reference existing.
    $identity_types = $this->getIdentityTypes();
    foreach ($identity_types as $entity_type_id => $bundles) {
      $referencable_bundles = $this->entityTypeHasBundles($entity_type_id) ? $bundles : [];
      $count = $this->countRngReferenceableEntities($entity_type_id, $referencable_bundles);
      if ($count > 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function countProxyIdentities() {
    $total = 0;

    foreach ($this->getIdentityTypes() as $entity_type_id => $bundles) {
      if ($this->entityTypeHasBundles($entity_type_id)) {
        $total += $this->countRngReferenceableEntities($entity_type_id, $bundles);
      }
      elseif (!empty($bundles)) {
        $total += $this->countRngReferenceableEntities($entity_type_id);
      }
    }

    return $total;
  }

  /**
   * Count referencable entities using a rng_register entity selection plugin.
   *
   * @param string $entity_type_id
   *   An identity entity type ID.
   * @param array $bundles
   *   (optional) An array of bundles.
   *
   * @return int
   *   The number of referencable entities.
   */
  protected function countRngReferenceableEntities($entity_type_id, $bundles = []) {
    $selection_groups = $this->selectionPluginManager
      ->getSelectionGroups($entity_type_id);

    if (isset($selection_groups['rng_register'])) {
      $options = [
        'target_type' => $entity_type_id,
        'handler' => 'rng_register',
        'handler_settings' => [
          'event_entity_type' => $this->getEvent()->getEntityTypeId(),
          'event_entity_id' => $this->getEvent()->id(),
        ],
      ];

      if (!empty($bundles)) {
        $options['handler_settings']['target_bundles'] = $bundles;
      }

      return $this->selectionPluginManager
        ->getInstance($options)
        ->countReferenceableEntities();
    }

    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentityTypes() {
    $event_type = $this->getEventType();

    $result = [];
    $identity_types_available = $this->rngConfiguration->getIdentityTypes();
    foreach ($identity_types_available as $entity_type_id) {
      $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle => $info) {
        if ($event_type->canIdentityTypeReference($entity_type_id, $bundle)) {
          $result[$entity_type_id][] = $bundle;
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatableIdentityTypes() {
    $event_type = $this->getEventType();

    $result = [];
    $identity_types_available = $this->rngConfiguration->getIdentityTypes();
    foreach ($identity_types_available as $entity_type_id) {
      $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle => $info) {
        if ($event_type->canIdentityTypeCreate($entity_type_id, $bundle)) {
          $result[$entity_type_id][] = $bundle;
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function identitiesCanRegister($entity_type, array $entity_ids) {
    $identity_types = $this->getIdentityTypes();
    if (isset($identity_types[$entity_type])) {
      $options = [
        'target_type' => $entity_type,
        'handler' => 'rng_register',
        'handler_settings' => [
          'event_entity_type' => $this->getEvent()->getEntityTypeId(),
          'event_entity_id' => $this->getEvent()->id(),
        ],
      ];

      if ($this->entityTypeHasBundles($entity_type)) {
        $options['handler_settings']['target_bundles'] = $identity_types[$entity_type];
      }

      /* @var $selection \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface */
      $selection = $this->selectionPluginManager->getInstance($options);
      return $selection->validateReferenceableEntities($entity_ids);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function addDefaultAccess() {
    $rules = $this->getDefaultRules('rng_event.register');
    foreach ($rules as $rule) {
      $rule->save();
    }
  }

  /**
   * Determine whether an entity type uses a separate bundle entity type.
   *
   * @param string $entity_type_id
   *   An entity type Id.
   *
   * @return bool
   *   Whether an entity type uses a separate bundle entity type.
   */
  protected function entityTypeHasBundles($entity_type_id) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    return ($entity_type->getBundleEntityType() !== NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function createDefaultEventMessages() {
    // Get Default messages for this Event type.
    $default_messages = $this->getEventType()->getDefaultMessages();
    if ($default_messages) {
      foreach ($default_messages as $default_message) {
        // Create Event Messages from Default Messages.
        $template_collection = TemplateCollection::create();
        $template_collection->save();
        $this->courier_manager->addTemplates($template_collection);
        $template_collection->setOwner($this->getEvent());
        $template_collection->save();

        $templates = $template_collection->getTemplates();
        /** @var \Drupal\courier\EmailInterface $courier_email */
        $courier_email = $templates[0];
        $courier_email->setSubject($default_message['subject']);
        $courier_email->setBody($default_message['body']);
        $courier_email->save();

        $rule = Rule::create([
          'event' => ['entity' => $this->getEvent()],
          'trigger_id' => $default_message['trigger'],
        ]);
        $rule->setIsActive($default_message['status']);

        $actionPlugin = $this->action_manager->createInstance('rng_courier_message');
        $configuration = $actionPlugin->getConfiguration();
        $configuration['template_collection'] = $template_collection->id();
        $action = RuleComponent::create([])
          ->setPluginId($actionPlugin->getPluginId())
          ->setConfiguration($configuration)
          ->setType('action');
        $rule->addComponent($action);
        $rule->save();

        // Handle custom date trigger.
        if ($default_message['trigger'] == 'rng:custom:date') {
          $rule_component = RuleComponent::create()
            ->setRule($rule)
            ->setType('condition')
            ->setPluginId('rng_rule_scheduler');
          $rule_component->save();
          // Save the ID into config.
          $rule_component->setConfiguration([
            'rng_rule_component' => $rule_component->id(),
          ]);
          $rule_component->save();
        }
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function getDateString() {
    $event_type = $this->getEventType();
    $start_field = $event_type->getEventStartDateField();
    $end_field = $event_type->getEventEndDateField();
    $event = $this->getEvent();
    $start = $event->get($start_field)->value;
    $count = $event->get($end_field)->count();
    $end_value = $event->get($end_field)->get($count - 1);
    if (!empty($end_value->end_value)) {
      $end = $end_value->end_value;
    }
    else {
      $end = $end_value->value;
    }
    $start_date = date('F j, Y', strtotime($start));
    $end_date = date('F j, Y', strtotime($end));
    if ($start_date == $end_date) {
      return $start_date;
    }
    return date('F j', strtotime($start)) . ' - ' . date('j, Y', strtotime($end));

  }

  /**
   * @inheritDoc
   */
  public function isPastEvent($use_end_date = FALSE) {
    $event_type = $this->getEventType();
    $event = $this->getEvent();
    if ($use_end_date) {
      $end_field = $event_type->getEventEndDateField();
      $count = $event->get($end_field)->count();
      $end_value = $event->get($end_field)->get($count - 1);
      if (!empty($end_value->end_value)) {
        $compare_date = $end_value->end_value;
      }
      else {
        $compare_date = $end_value->value;
      }
    }
    else {
      $start_field = $event_type->getEventStartDateField();
      $compare_date = $event->get($start_field)->value;
    }
    return strtotime($compare_date) < time();

  }
}
