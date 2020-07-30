<?php

namespace Drupal\rng\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the registrant type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "registrant_type",
 *   label = @Translation("Registrant type"),
 *   admin_permission = "administer registrant types",
 *   config_prefix = "registrant_type",
 *   bundle_of = "registrant",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\rng\Lists\RegistrantTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\rng\Form\Entity\RegistrantTypeForm",
 *       "add" = "Drupal\rng\Form\Entity\RegistrantTypeForm",
 *       "edit" = "Drupal\rng\Form\Entity\RegistrantTypeForm",
 *       "delete" = "Drupal\rng\Form\Entity\RegistrantTypeDeleteForm"
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/rng/registrant_types/add",
 *     "canonical" = "/admin/structure/rng/registrant_types/manage/{registrant_type}",
 *     "delete-form" = "/admin/structure/rng/registrant_types/manage/{registrant_type}/delete",
 *     "edit-form" = "/admin/structure/rng/registrant_types/manage/{registrant_type}",
 *     "admin-form" = "/admin/structure/rng/registrant_types/manage/{registrant_type}",
 *     "collection" = "/admin/structure/rng/registrant_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "label_pattern",
 *   }
 * )
 */
class RegistrantType extends ConfigEntityBundleBase implements RegistrantTypeInterface {

  /**
   * The machine name of this registrant type.
   *
   * @var string
   */
  public $id;

  /**
   * The human readable name of this registrant type.
   *
   * @var string
   */
  public $label;

  /**
   * A brief description of this registrant type.
   *
   * @var string
   */
  public $description;

  /**
   * A token-based pattern to use for the entity label if no identity
   *
   * @var string
   */
  public $label_pattern;
}
