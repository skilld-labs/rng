<?php

namespace Drupal\rng\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rng\EventManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\Date;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("event_date_string_field")
 */
class EventDateStringField extends Date {

  /**
   * The start field name.
   *
   * @var string
   *   The name of the start field name -- this is used for sorting/filtering.
   */
  protected $fieldName;

  /**
   * EventManager service.
   *
   * @var \Drupal\rng\EventManagerInterface
   */
  protected $eventManager;

  /**
   * Views Join Manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * EntityFieldManager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * EntityTypeManager service.
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              DateFormatterInterface $date_formatter,
                              EventManagerInterface $event_manager,
                              EntityFieldManagerInterface $entityFieldManager,
                              EntityTypeManagerInterface $entityTypeManager,
                              ViewsHandlerManager $joinManager,
                              Connection $database) {
    $this->eventManager = $event_manager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->joinManager = $joinManager;
    $this->database = $database;
    parent::__construct($configuration, $plugin_id, $plugin_definition,
      $date_formatter,
      $entityTypeManager->getStorage('date_format')
      );
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('rng.event_manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.views.join'),
      $container->get('database')
    );
  }

  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    if (empty($options['entity_type'])) {
      return;
    }
    $type = $options['entity_type'];
    $bundle = $options['entity_bundle'];
    $event = $this->eventManager->eventType($type, $bundle);
    if ($event) {
      $field = $event->getEventStartDateField();
      $definitions = $this->entityFieldManager->getFieldDefinitions($this->configuration['entity_type'],
        $this->options['entity_bundle']);
      $field_def = $definitions[$field];

      $table = $type . '__' . $field;
      $fieldname = $field . '_value';
      $this->realField = $fieldname;
      $this->table = $table;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return parent::query();
    $alias = 'date_table_min';
    $subquery = $this->database->select($this->table, $alias);
    $subquery->addField($alias, 'entity_id');
    $subquery->addExpression('MIN('. $alias . '.'. $this->realField .')', 'min_date');
    $subquery->groupBy($alias . '.entity_id');

    $join_table = $this->options['entity_type'] . '_field_data';
    $id_field = $this->entityTypeManager->getDefinition($this->options['entity_type'])->getKey('id');
    $definition = [
      'table formula' => $subquery,
      'field' => 'entity_id',
      'left_table' => $join_table,
      'left_field' => $id_field,
      'adjust' => TRUE,
    ];
    $join = $this->joinManager->createInstance('standard', $definition);
    $this->query->addRelationship('event_min_date', $join, $join_table);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['entity_bundle'] = ['default' => ''];
    return $options;
  }

  /**up
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $bundles = $this->eventManager->eventTypeWithEntityType($this->configuration['entity_type']);
    $options = [];
    foreach ($bundles as $k => $v) {
      [$content_type, $bundle] = explode('.', $k);
      $options[$bundle] = $bundle;
    }
    $form['entity_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the @event type defining the date field to use.',
        ['@event' => $content_type]),
      '#default_value' => $this->options['entity_bundle'],
      '#options' => $options,
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $options = &$form_state->getValue('options');

    parent::submitOptionsForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Make sure we are looking at the correct entity.
    $object = $this->getEntity($values);
    if (empty($object) || $object->getEntityTypeId() != $this->configuration['entity_type']) {
      return;
    }
    $event_meta = $this->eventManager->getMeta($object);

    if ($event_meta && method_exists($event_meta, 'getDateString')) {
      return $event_meta->getDateString();
    }

  }

}
