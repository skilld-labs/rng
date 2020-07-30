<?php

namespace Drupal\rng\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Drupal\rng\Entity\RegistrationTypeInterface;

/**
 * Checks new registrations are permitted on an event.
 */
class RegistrationAddAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a EventRegistrationAllowedCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks new registrations are permitted on an event.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, RegistrationTypeInterface $registration_type = NULL) {
    if ($event = $route->getDefault('event')) {
      $context = ['event' => $route_match->getParameter($event)];
      $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');
      if ($registration_type) {
        return $access_control_handler->createAccess($registration_type->id(), $account, $context, TRUE);
      }
      else {
        return $access_control_handler->createAccess(NULL, $account, $context, TRUE);
      }
    }
    return AccessResult::forbidden();
  }

}
