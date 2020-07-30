<?php

namespace Drupal\rng\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\rng\RngConfigurationInterface;
use Drupal\rng\EventManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\rng\Entity\EventTypeRule;
use Drupal\rng\Entity\RegistrantType;

/**
 * Form controller for event config entities.
 */
class EventTypeForm extends EntityForm {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Bundle Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;
  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

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
   * Constructs a EventTypeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param EntityTypeBundleInfoInterface $bundle_info
   *   The Bundle Info.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\rng\RngConfigurationInterface $rng_configuration
   *   The RNG configuration service.
   * @param \Drupal\rng\EventManagerInterface $event_manager
   *   The RNG event manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $bundle_info, ModuleHandlerInterface $module_handler, EntityDisplayRepositoryInterface $entity_display_repository, RngConfigurationInterface $rng_configuration, EventManagerInterface $event_manager) {
    $this->entityManager = $entity_manager;
    $this->bundleInfo = $bundle_info;
    $this->moduleHandler = $module_handler;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->rngConfiguration = $rng_configuration;
    $this->eventManager = $event_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('module_handler'),
      $container->get('entity_display.repository'),
      $container->get('rng.configuration'),
      $container->get('rng.event_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\rng\Entity\EventTypeInterface $event_type */
    $event_type = $this->entity;

    if (!$event_type->isNew()) {
      $form['#title'] = $this->t('Edit event type %label configuration', [
        '%label' => $event_type->label(),
      ]);
    }

    if ($event_type->isNew()) {
      $bundle_options = [];
      // Generate a list of fieldable bundles which are not events.
      foreach ($this->entityManager->getDefinitions() as $entity_type) {
        if ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')) {
          foreach ($this->bundleInfo->getBundleInfo($entity_type->id()) as $bundle => $bundle_info) {
            if (!$this->eventManager->eventType($entity_type->id(), $bundle)) {
              $bundle_options[(string) $entity_type->getLabel()][$entity_type->id() . '.' . $bundle] = $bundle_info['label'];
            }
          }
        }
      }

      if ($this->moduleHandler->moduleExists('node')) {
        $form['#attached']['library'][] = 'rng/rng.admin';
        $form['entity_type'] = [
          '#type' => 'radios',
          '#options' => NULL,
          '#title' => $this->t('Event entity type'),
          '#required' => TRUE,
          // Kills \Drupal\Core\Render\Element\Radios::processRadios.
          '#process' => [],
        ];
        $form['entity_type']['node']['radio'] = [
          '#type' => 'radio',
          '#title' => $this->t('Create a new content type'),
          '#description' => $this->t('Create a content type to use as an event type.'),
          '#return_value' => "node",
          '#parents' => ['entity_type'],
          '#default_value' => 'node',
        ];

        $form['entity_type']['existing']['radio'] = [
          '#type' => 'radio',
          '#title' => $this->t('Use existing bundle'),
          '#description' => $this->t('Use an existing entity/bundle combination.'),
          '#return_value' => "existing",
          '#parents' => ['entity_type'],
          '#default_value' => '',
        ];

        $form['entity_type']['existing']['container'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['rng-radio-indent'],
          ],
        ];
      }

      $form['entity_type']['existing']['container']['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Bundle'),
        '#options' => $bundle_options,
        '#default_value' => $event_type->id(),
        '#disabled' => !$event_type->isNew(),
        '#empty_option' => $bundle_options ? NULL : t('No Bundles Available'),
      ];
    }

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    ];

    // Mirror permission.
    $form['access']['mirror_update'] = [
      '#group' => 'settings',
      '#type' => 'checkbox',
      '#title' => t('Mirror manage registrations with update permission'),
      '#description' => t('Allow users to <strong>manage registrations</strong> if they have <strong>update</strong> permission on an event entity.'),
      '#default_value' => (boolean) (($event_type->getEventManageOperation() !== NULL) ? $event_type->getEventManageOperation() : TRUE),
    ];

    // Allow Anonymous Registrants?
    $form['allow_anon_registrants'] = [
      '#group' => 'settings',
      '#type' => 'checkbox',
      '#title' => t('Allow anonymous registrants without saving to other identities?'),
      '#description' => t('Allow editing registrant data as text fields on a registration. Add fields to the registrant type for information you would like to collect.'),
      '#default_value' => (boolean) $event_type->getAllowAnonRegistrants(),
    ];

    // Auto Sync Registrants
    $form['auto_sync_registrants'] = [
      '#group' => 'settings',
      '#type' => 'checkbox',
      '#title' => t('Sync matching field data between registrant and registrant identity'),
      '#description' => t('If there are empty fields on either the registrant or an identity, copy data from the other when a registrant is updated. This can streamline views of attendee data.'),
      '#default_value' => (boolean) $event_type->getAutoSyncRegistrants(),
    ];

    // Auto Attach User Identities
    $form['auto_attach_users'] = [
      '#group' => 'settings',
      '#type' => 'checkbox',
      '#title' => t('Automatically add user identities to anonymous registrants if email matches'),
      '#description' => t('If an email field in a registrant matches a user account, automatically add the user account as the identity.'),
      '#default_value' => (boolean) $event_type->getAutoAttachUsers(),
      '#states' => [
        'invisible' => [
          ':input[name="allow_anon_registrants"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Registrant email field
    $form['registrant_email_field'] = [
      '#group' => 'settings',
      '#type' => 'textfield',
      '#title' => t('Registrant email field'),
      '#description' => t('Machine name of a field on the registrant to use when looking up a user account.'),
      '#default_value' => $event_type->getRegistrantEmailField(),
      '#states' => [
        'invisible' => [
          ':input[name="auto_attach_users"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Event date fields
    $form['event_date_field_start'] = [
      '#group' => 'settings',
      '#type' => 'textfield',
      '#title' => t('Event Start Date field'),
      '#description' => t('Machine name of a field on the event to use as the event start date.'),
      '#default_value' => $event_type->getEventStartDateField(),
    ];
    $form['event_date_field_end'] = [
      '#group' => 'settings',
      '#type' => 'textfield',
      '#title' => t('Event End Date field'),
      '#description' => t('Machine name of a field on the event to use as the event end date. Will be combined into a "friendly" date string on registrations.'),
      '#default_value' => $event_type->getEventEndDateField(),
    ];

    $registrant_types = [];
    foreach (RegistrantType::loadMultiple() as $registrant_type) {
      /** @var \Drupal\rng\Entity\RegistrantTypeInterface $registrant_type */
      $registrant_types[$registrant_type->id()] = $registrant_type->label();
    }

    $form['registrants'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Registrants'),
      '#tree' => TRUE,
    ];

    // Default registrant type.
    $form['registrants']['registrant_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default registrant type'),
      '#description' => $this->t('Registrant type used for new registrants associated with this event type.'),
      '#required' => TRUE,
      '#options' => $registrant_types,
      '#default_value' => $event_type->getDefaultRegistrantType(),
    ];

    $form['registrants']['registrants'] = [
      '#type' => 'table',
      '#header' => [
        [
          'data' => $this->t('Person type'),
        ],
        [
          'data' => $this->t('Permit inline creation of entities'),
          'class' => ['checkbox'],
        ],
        [
          'data' => $this->t('Form display mode'),
        ],
        [
          'data' => $this->t('Permit referencing existing entities'),
          'class' => ['checkbox'],
        ],
      ],
      '#empty' => $this->t('There are no people types available.'),
    ];

    foreach ($this->rngConfiguration->getIdentityTypes() as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle => $info) {
        $t_args = [
          '@bundle' => $info['label'],
          '@entity_type' => $entity_type->getLabel(),
        ];

        $row = [];
        $row['people_type']['#markup'] = $this->t('@bundle (@entity_type)', $t_args);

        $row['create'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Permit inline creation of @bundle entities', $t_args),
          '#title_display' => 'invisible',
          '#default_value' => $event_type->canIdentityTypeCreate($entity_type_id, $bundle),
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
        ];

        $row['entity_form_mode'] = [
          '#type' => 'select',
          '#title' => $this->t('Form display mode used when the entity is created inline.', $t_args),
          '#title_display' => 'invisible',
          '#default_value' => $event_type->getIdentityTypeEntityFormMode($entity_type_id, $bundle),
          '#options' => $this->entityDisplayRepository->getFormModeOptionsByBundle($entity_type_id, $bundle),
        ];

        $row['existing'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Permit referencing existing @bundle entities', $t_args),
          '#title_display' => 'invisible',
          '#default_value' => $event_type->canIdentityTypeReference($entity_type_id, $bundle),
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
        ];

        $row_key = "$entity_type_id:$bundle";
        $form['registrants']['registrants'][$row_key] = $row;
      }
    }

    // Check if user people type is enabled, then apply some special handling.
    if (isset($form['registrants']['registrants']['user:user'])) {
      // Blacklist user creation. It does not work because it is special.
      $form['registrants']['registrants']['user:user']['create']['#access'] = FALSE;

      // Auto check existing references for users.
      if ($event_type->isNew()) {
        $form['registrants']['registrants']['user:user']['existing']['#default_value'] = TRUE;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->entity = $this->buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\rng\Entity\EventTypeInterface $event_type */
    $event_type = $this->getEntity();

    if ($event_type->isNew()) {
      if ($this->moduleHandler->moduleExists('node') && ($form_state->getValue('entity_type') == 'node')) {
        $node_type = $this->createContentType('Event');
        $t_args = [
          '%label' => $node_type->label(),
          ':url' => $node_type->toUrl()->toString(),
        ];
        $this->messenger()->addMessage(t('The content type <a href=":url">%label</a> has been added.', $t_args));
        $event_type->setEventEntityTypeId($node_type->getEntityType()->getBundleOf());
        $event_type->setEventBundle($node_type->id());
      }
      else {
        $bundle = explode('.', $form_state->getValue('bundle'));
        $event_type->setEventEntityTypeId($bundle[0]);
        $event_type->setEventBundle($bundle[1]);
      }
    }

    foreach ($form_state->getValue(['registrants', 'registrants']) as $row_key => $row) {
      [$entity_type, $bundle] = explode(':', $row_key);
      $event_type->setIdentityTypeCreate($entity_type, $bundle, !empty($row['create']));
      $event_type->setIdentityTypeReference($entity_type, $bundle, !empty($row['existing']));
      $event_type->setIdentityTypeEntityFormMode($entity_type, $bundle, $row['entity_form_mode']);
    }

    $event_type->setDefaultRegistrantType($form_state->getValue(['registrants', 'registrant_type']));
    // Set to the access operation for event.
    $op = $form_state->getValue('mirror_update') ? 'update' : '';
    $event_type->setEventManageOperation($op)
      ->setAllowAnonRegistrants($form_state->getValue('allow_anon_registrants'))
      ->setAutoSyncRegistrants($form_state->getValue('auto_sync_registrants'))
      ->setAutoAttachUsers($form_state->getValue('auto_attach_users'))
      ->setRegistrantEmailField($form_state->getValue('registrant_email_field'))
      ->setEventStartDateField($form_state->getValue('event_date_field_start'))
      ->setEventEndDateField($form_state->getValue('event_date_field_end'));

    $status = $event_type->save();

    // Create default rules.
    if ($status == SAVED_NEW) {
      $this->createDefaultRules($event_type->getEventEntityTypeId(), $event_type->getEventBundle());
    }

    $message = ($status == SAVED_UPDATED) ? '%label event type updated.' : '%label event type added.';
    $url = $event_type->toUrl();
    $t_args = ['%label' => $event_type->id(), 'link' => (Link::fromTextAndUrl($this->t('Edit'), $url))->toString()];

    $this->messenger()->addMessage($this->t($message, $t_args));
    $this->logger('rng')->notice($message, $t_args);
  }

  /**
   * Creates a content type.
   *
   * Attempts to create a content type with ID $prefix, $prefix_1, $prefix_2...
   *
   * @param string $prefix
   *   A string prefix for the node type ID.
   *
   * @return \Drupal\node\NodeTypeInterface
   *   A node type entity.
   */
  protected function createContentType($prefix) {
    // Generate a unique ID.
    $i = 0;
    $separator = '_';
    $id = $prefix;
    while (NodeType::load(mb_strtolower($id))) {
      $i++;
      $id = $prefix . $separator . $i;
    }

    $node_type = NodeType::create([
      'type' => mb_strtolower($id),
      'name' => $id,
    ]);
    $node_type->save();
    return $node_type;
  }

  /**
   * Create default rules for an event type.
   *
   * @param $entity_type_id
   *   An entity type ID.
   * @param $bundle
   *   A bundle ID.
   */
  public static function createDefaultRules($entity_type_id, $bundle) {
    // User Role.
    /** @var \Drupal\rng\Entity\EventTypeRuleInterface $rule */
    $rule = EventTypeRule::create([
      'trigger' => 'rng_event.register',
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'machine_name' => 'user_role',
    ]);
    $rule->setCondition('role', [
      'id' => 'rng_user_role',
      'roles' => [],
    ]);
    $rule->setAction('registration_operations', [
      'id' => 'registration_operations',
      'configuration' => [
        'operations' => [
          'create' => TRUE,
        ],
      ],
    ]);
    $rule->save();

    // Registrant.
    $rule = EventTypeRule::create([
      'trigger' => 'rng_event.register',
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'machine_name' => 'registrant',
    ]);
    $rule->setCondition('identity', [
      'id' => 'rng_registration_identity',
    ]);
    $rule->setAction('registration_operations', [
      'id' => 'registration_operations',
      'configuration' => [
        'operations' => [
          'view' => TRUE,
          'update' => TRUE,
        ],
      ],
    ]);
    $rule->save();

    // Event managers.
    $rule = EventTypeRule::create([
      'trigger' => 'rng_event.register',
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'machine_name' => 'event_manager',
    ]);
    $rule->setCondition('operation', [
      'id' => 'rng_event_operation',
      'operations' => ['manage event' => TRUE],
    ]);
    $rule->setAction('registration_operations', [
      'id' => 'registration_operations',
      'configuration' => [
        'operations' => [
          'create' => TRUE,
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
    ]);
    $rule->save();
  }

}
