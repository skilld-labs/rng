<?php

namespace Drupal\rng\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for registration types.
 */
class RegistrationTypeForm extends EntityForm {

  /**
   * The registration type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $registrationTypeStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->registrationTypeStorage = $container->get('entity_type.manager')
      ->getStorage('registration_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $registration_type = $this->entity;

    if (!$registration_type->isNew()) {
      $form['#title'] = $this->t('Edit registration type %label', [
        '%label' => $registration_type->label(),
      ]);
    }

    // Build the form.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $registration_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $registration_type->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => 'The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores.',
      ],
      '#disabled' => !$registration_type->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $registration_type->description,
      '#description' => t('Description will be displayed when a user is choosing which registration type to use for an event.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Callback for `id` form element in RegistrationTypeForm->buildForm.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    $query = $this->registrationTypeStorage->getQuery();
    return (bool) $query->condition('id', $entity_id)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $registration_type = $this->getEntity();
    $status = $registration_type->save();

    $message = ($status == SAVED_UPDATED) ? '%label registration type was updated.' : '%label registration type was added.';
    $url = $registration_type->toUrl();
    $t_args = ['%label' => $registration_type->label(), 'link' => Link::fromTextAndUrl(t('Edit'), $url)];

    $this->messenger()->addMessage($this->t($message, $t_args));
    $this->logger('rng')->notice($message, $t_args);

    $form_state->setRedirect('rng.registration_type.overview');
  }

}
