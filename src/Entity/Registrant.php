<?php

namespace Drupal\rng\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rng\Exception\InvalidRegistrant;

/**
 * Defines the registrant entity class.
 *
 * @ContentEntityType(
 *   id = "registrant",
 *   label = @Translation("Registrant"),
 *   bundle_label = @Translation("Registrant type"),
 *   bundle_entity_type = "registrant_type",
 *   handlers = {
 *     "storage_schema" = "Drupal\rng\RegistrantStorageSchema",
 *     "views_data" = "Drupal\rng\Views\RegistrantViewsData",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\rng\Routing\RegistrantRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\rng\Form\Entity\RegistrantForm",
 *       "edit" = "Drupal\rng\Form\Entity\RegistrantForm",
 *       "compact" = "Drupal\rng\Form\Entity\RegistrantForm",
 *       "delete" = "Drupal\rng\Form\Entity\RegistrantDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer rng",
 *   base_table = "registrant",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type"
 *   },
 *   field_ui_base_route = "entity.registrant_type.edit_form",
 *   links = {
 *     "canonical" = "/registrant/{registrant}",
 *     "edit-form" = "/registrant/{registrant}/edit",
 *     "delete-form" = "/registrant/{registrant}/delete"
 *   },
 * )
 */
class Registrant extends ContentEntityBase implements RegistrantInterface {

  /**
   * {@inheritdoc}
   */
  public function getRegistration() {
    return $this->get('registration')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setRegistration(RegistrationInterface $registration) {
    $this->set('registration', ['entity' => $registration]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentity() {
    return $this->get('identity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentityId() {
    return [
      'entity_type' => $this->get('identity')->target_type,
      'entity_id' => $this->get('identity')->target_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentity(EntityInterface $entity) {
    $this->set('identity', ['entity' => $entity]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearIdentity() {
    $this->identity->setValue(NULL);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasIdentity(EntityInterface $entity) {
    $keys = $this->getIdentityId();
    return $entity->getEntityTypeId() == $keys['entity_type'] && $entity->id() == $keys['entity_id'];
  }

  /**
   * {@inheritDoc}
   *
   * If a value is set on the identity and blank on the registrant, copy values
   * from identity to registrant, and vice-versa.
   */
  public function preSave(EntityStorageInterface $storage) {
    if (!$this->getRegistration()) {
      throw new InvalidRegistrant('Registrant created with no registration.');
    }
    $event_type = $this->getRegistration()->getEventMeta()->getEventType();

    if ($this->event->isEmpty()) {
      $registration = $this->getRegistration();
      $this->event->setValue([
        'target_id' => $registration->event->target_id,
        'target_type' => $registration->event->target_type,
      ]);
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getIdentity();

    if (!$entity && !$event_type->getAllowAnonRegistrants()) {
      throw new InvalidRegistrant('Registrant created with no identity, and anonymous registrants are not allowed.');
    }

    if (!$entity && $event_type->getAutoAttachUsers()) {
      $email_field = $event_type->getRegistrantEmailField();
      if (!$this->get($email_field)->isEmpty()) {
        $email = $this->get($email_field)->value;
        $entity = user_load_by_mail($email);
        if ($entity) {
          $this->setIdentity($entity);
        }
      }
    }

    if ($entity && $event_type->getAutoSyncRegistrants()) {
      $fields = $this->getFields(FALSE);
      $entity_fields = $entity->getFields(FALSE);
      $entity_changed = FALSE;
      foreach ($fields as $name => $field) {
        if (isset($entity_fields[$name])) {
          if (empty($field) && !$entity_fields[$name]) {
            $this->set($name, $entity_fields[$name]);
          }
          elseif (empty($entity_fields[$name]) && !empty($field)) {
            $entity->set($name, $field);
            $entity_changed = TRUE;
          }
        }
      }

      if ($entity_changed) {
        $entity->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getRegistrantsIdsForIdentity(EntityInterface $identity) {
    return \Drupal::entityQuery('registrant')
      ->accessCheck(FALSE)
      ->condition('identity__target_type', $identity->getEntityTypeId(), '=')
      ->condition('identity__target_id', $identity->id(), '=')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $identity = $this->getIdentity();
    if ($identity) {
      return t('@type @id', [
        '@type' => $identity->getEntityTypeId(),
        '@id' => $identity->label(),
      ]);
    } 
    $registration = $this->getRegistration();
    $pattern = $this->type->entity->label_pattern;
    if (!empty($pattern)) {
      $label = \Drupal::token()->replace($pattern,['registrant'=>$this, 'registration'=>$registration]);
      if (!empty(trim($label))) {
        if (strstr($label, '[') != FALSE) {
          return t(' ');
        }
        return $label;
      }
    }
    if ($registration) {
      return $registration->label();
    }
    return t('New registrant');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['registration'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Registration'))
      ->setDescription(t('The registration associated with this registrant.'))
      ->setSetting('target_type', 'registration')
      ->setCardinality(1)
      ->setReadOnly(TRUE);

    $fields['identity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Identity'))
      ->setDescription(t('The person associated with this registrant.'))
      ->setSetting('exclude_entity_types', 'true')
      ->setSetting('entity_type_ids', ['registrant', 'registration'])
      ->setCardinality(1)
      ->setReadOnly(TRUE);

    $fields['event'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Event'))
      ->setDescription(t('The event for the registrant.'))
      ->setSetting('exclude_entity_types', 'true')
      ->setSetting('entity_type_ids', ['registrant', 'registration'])
      ->setDescription(t('The relationship between this registrant and an event.'))
      ->setRevisionable(TRUE)
      ->setReadOnly(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
    ->setLabel(new TranslatableMarkup('Confirmed'))
    ->setRevisionable(TRUE)
    ->setTranslatable(TRUE)
    ->setDefaultValue(TRUE)
    ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * @inheritDoc
   */
  public function getEvent() {
    return $this->get('event')->entity;
  }

  /**
   * @inheritDoc
   */
  public function setEvent(ContentEntityInterface $event) {
    $this->set('event', ['entity' => $event]);
    return $this;
  }
}
