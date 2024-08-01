<?php

namespace Drupal\rng\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rng\Exception\MaxRegistrantsExceededException;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\UserInterface;

/**
 * Defines the registration entity class.
 *
 * @ContentEntityType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   bundle_label = @Translation("Registration type"),
 *   base_table = "registration",
 *   data_table = "registration_field_data",
 *   revision_table = "registration_revision",
 *   revision_data_table = "registration_field_revision",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "published" = "status",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "uid" = "uid"
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\rng\Views\RegistrationViewsData",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\rng\AccessControl\RegistrationAccessControlHandler",
 *     "list_builder" = "\Drupal\rng\Lists\RegistrationListBuilder",
 *     "form" = {
 *       "default" = "Drupal\rng\Form\RegistrationForm",
 *       "add" = "Drupal\rng\Form\RegistrationForm",
 *       "edit" = "Drupal\rng\Form\RegistrationForm",
 *       "delete" = "Drupal\rng\Form\RegistrationDeleteForm",
 *       "registrants" = "Drupal\rng\Form\RegistrationRegistrantEditForm"
 *     },
 *     "storage" = "Drupal\rng\RegistrationStorage",
 *   },
 *   bundle_entity_type = "registration_type",
 *   admin_permission = "administer registration entity",
 *   permission_granularity = "bundle",
 *   links = {
*     "canonical" = "/registration/{registration}",
 *     "edit-form" = "/registration/{registration}/edit",
 *     "delete-form" = "/registration/{registration}/delete"
 *   },
 *   field_ui_base_route = "entity.registration_type.edit_form"
 * )
 */
class Registration extends ContentEntityBase implements RegistrationInterface {

  use EntityChangedTrait;

  /**
   * Internal cache of identities to associate with this rule when it is saved.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $identities_unsaved = [];

  /**
   * {@inheritdoc}
   */
  public function getEvent() {
    return $this->get('event')->entity;
  }

  /**
   * {@inheritDoc}
   */
  public function getEventMeta() {
    // Add group defaults event settings.
    /* @var $event_manager \Drupal\rng\EventManagerInterface */
    $event_manager = \Drupal::service('rng.event_manager');
    return $event_manager->getMeta($this->getEvent());
  }

  /**
   * {@inheritdoc}
   */
  public function setEvent(ContentEntityInterface $entity) {
    $this->set('event', ['entity' => $entity]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $owner = $this->getOwner();
    $qty = $this->getRegistrantQty();
    $registrations = '';
    if ($qty) {
      $registrations = '[' . $qty . ']';
    }
    if ($owner) {
      return t('@owner @regs', [
        '@owner' => $owner->label(),
        '@regs' => $registrations,
      ]);
    }
    return t('New registration');
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfirmed() {
    return (bool) $this->getEntityKey('published');
  }

  /**
   * {@inheritdoc}
   */
  public function setConfirmed($confirmed) {
    $this->set('status', (bool) $confirmed);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->getEntityKey('owner');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrantIds() {
    return $this->registrant_ids = \Drupal::entityQuery('registrant')
      ->accessCheck(FALSE)
      ->condition('registration', $this->id(), '=')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrants() {
    if ($this->getRegistrantQty()) {
      $this->createStubs();
    }
    $registrants = \Drupal::entityTypeManager()->getStorage('registrant')
      ->loadMultiple($this->getRegistrantIds());
    if (!count($registrants)) {
      /** @var \Drupal\rng\RegistrantFactoryInterface $registrant_factory */
      $registrant_factory = \Drupal::service('rng.registrant.factory');

      // Always return at least one, even if it's not saved. This should only
      // run if there is not a defined number of registrants for this
      // registration, and no existing registrants.
      $registrant = $registrant_factory->createRegistrant([
        'event' => $this->getEvent(),
      ]);
      $registrant->setRegistration($this);

      $registrants[] = $registrant;
    }

    return $registrants;
  }

  /**
   * {@inheritdoc}
   */
  public function hasIdentity(EntityInterface $identity) {
    foreach ($this->identities_unsaved as $identity_unsaved) {
      if ($identity === $identity_unsaved) {
        return TRUE;
      }
    }
    foreach ($this->getRegistrants() as $registrant) {
      if ($registrant->hasIdentity($identity)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addIdentity(EntityInterface $identity) {
    if ($this->hasIdentity($identity)) {
      // Identity already exists on this registration.
      throw new \Exception('Duplicate identity on registration');
    }
    if (!$this->canAddRegistrants()) {
      throw new MaxRegistrantsExceededException('Cannot add another registrant to this registration.');
    }
    $this->identities_unsaved[] = $identity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroups() {
    return $this->groups->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function addGroup(GroupInterface $group) {
    // Do not add the group if it is already related.
    if (!in_array($group, $this->getGroups())) {
      if ($group->getEvent() != $this->getEvent()) {
        throw new \Exception('Group and registration events do not match.');
      }
      $this->groups->appendItem($group);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeGroup($group_id) {
    foreach ($this->groups->getValue() as $key => $value) {
      if ($value['target_id'] == $group_id) {
        $this->groups->removeItem($key);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Registration ID'))
      ->setDescription(t('The registration ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The registration UUID.'))
      ->setReadOnly(TRUE);

    $fields['vid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The registration revision ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Confirmed'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 90,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The registration type.'))
      ->setSetting('target_type', 'registration_type')
      ->setReadOnly(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The registration language code.'))
      ->setRevisionable(TRUE);

    $fields['event'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Event'))
      ->setDescription(t('The event for the registration.'))
      ->setSetting('exclude_entity_types', 'true')
      ->setSetting('entity_type_ids', ['registrant', 'registration'])
      ->setDescription(t('The relationship between this registration and an event.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE);

    $fields['groups'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Groups'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDescription(t('The groups the registration is assigned.'))
      ->setSetting('target_type', 'registration_group')
      ->addConstraint('RegistrationGroupSibling');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('Time the Registration was created.'))
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated on'))
      ->setDescription(t('The time Registration was last updated.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The owner of the registration.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\rng\Entity\Registration::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    /**
     * Optional registrant qty field, used for rendering the registrant form. If
     * set and not zero, users cannot add more than this number of registrants
     * to the registration.
     */
    $fields['registrant_qty'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Registrant Qty'))
      ->setDescription(t('Number of registrants on this registration'))
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->getEvent() instanceof ContentEntityBase) {
      throw new EntityMalformedException('Invalid or missing event on registration.');
    }
    $registrants = $this->getRegistrantIds();
    $count = $this->getRegistrantQty();
    if (!empty($count) && $count < count($registrants)) {
      throw new MaxRegistrantsExceededException('Too many registrants on this registration.');
    }

    $event_meta = $this->getEventMeta();
    if ($this->isNew()) {
      foreach ($event_meta->getDefaultGroups() as $group) {
        $this->addGroup($group);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    /** @var \Drupal\rng\RegistrantFactoryInterface $registrant_factory */
    $registrant_factory = \Drupal::service('rng.registrant.factory');

    foreach ($this->identities_unsaved as $k => $identity) {
      $registrant = $registrant_factory->createRegistrant([
        'event' => $this->getEvent(),
      ]);
      $registrant
        ->setRegistration($this)
        ->setIdentity($identity)
        ->save();
      unset($this->identities_unsaved[$k]);
    }
    $this->createStubs();
  }

  protected function createStubs() {
    $stub_count = $this->getRegistrantQty();
    if ($stub_count && $this->canAddRegistrants()) {
      /** @var \Drupal\rng\RegistrantFactoryInterface $registrant_factory */
      $registrant_factory = \Drupal::service('rng.registrant.factory');

      $registrant_count = count($this->getRegistrantIds());
      $stub_count -= $registrant_count;

      while ($stub_count) {
        $registrant = $registrant_factory->createRegistrant([
          'event' => $this->getEvent(),
        ]);
        $registrant
          ->setRegistration($this)
          ->save();
        $stub_count--;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    $registrant_storage = \Drupal::entityTypeManager()->getStorage('registrant');

    /** @var \Drupal\rng\RegistrationInterface $registration */
    foreach ($entities as $registration) {
      // Delete associated registrants.
      $ids = $registrant_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('registration', $registration->id(), '=')
        ->execute();
      $registrants = $registrant_storage->loadMultiple($ids);
      $registrant_storage->delete($registrants);
    }

    parent::preDelete($storage, $entities);
  }

  /**
   * @inheritDoc
   */
  public function getRegistrantQty() {
    return $this->get('registrant_qty')->value;
  }

  /**
   * @inheritDoc
   */
  public function setRegistrantQty($qty) {
    $registrants = $this->getRegistrantIds();
    if ($qty > 0) {
      if (count($registrants) > $qty) {
        throw new MaxRegistrantsExceededException('Cannot set registrant qty below number of current registrants.');
      }
      $event_meta = $this->getEventMeta();
      $max = $event_meta->getRegistrantsMaximum();
      if (!empty($max) && $max > -1 && $qty > $max) {
        throw new MaxRegistrantsExceededException('Cannot set registrations above event maximum');
      }
    }
    $this->set('registrant_qty', $qty);
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function canAddRegistrants() {
    $registrants = $this->getRegistrantIds();
    $qty = $this->getRegistrantQty();
    if ($qty) {
      return $qty > count($registrants);
    }
    return TRUE;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * @inheritDoc
   */
  public function getDateString() {
    $event_meta = $this->getEventMeta();
    return $event_meta->getDateString();
  }
}
