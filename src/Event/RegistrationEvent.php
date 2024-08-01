<?php

namespace Drupal\rng\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\rng\Entity\RegistrationInterface;

/**
 * Registration event.
 *
 * @see \Drupal\rng\Event\RegistrationEvents
 */
class RegistrationEvent extends Event {

  /**
   * The registration.
   *
   * @var \Drupal\rng\Entity\RegistrationInterface
   */
  protected $registration;

  /**
   * RegistrationEvent constructor.
   *
   * @param \Drupal\rng\Entity\RegistrationInterface $registration
   *   The registration.
   */
  public function __construct(RegistrationInterface $registration) {
    $this->registration = $registration;
  }

  /**
   * Get the registration.
   *
   * @return \Drupal\rng\Entity\RegistrationInterface
   *   The registration.
   */
  public function getRegistration() {
    return $this->registration;
  }

}
