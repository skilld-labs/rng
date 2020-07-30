<?php

namespace Drupal\rng\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\rng\Entity\Registrant;

/**
 * Tests registrant routes.
 *
 * @group rng
 */
class RngRegistrantRouteTest extends RngWebTestBase {

  /**
   * @inheritdoc
   */
  public static $modules = ['block', 'entity_test'];

  /**
   * The registration type for testing.
   *
   * @var \Drupal\rng\Entity\RegistrationTypeInterface
   */
  public $registrationType;

  /**
   * The event type for testing.
   *
   * @var \Drupal\rng\Entity\EventTypeInterface
   */
  public $eventType;

  /**
   * The registrant for testing.
   *
   * @var \Drupal\rng\Entity\RegistrantInterface
   */
  public $registrant;

  /**
   * Name of the test field attached to registrant entity.
   *
   * @var string
   */
  public $registrantTestField;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->eventType = $this->createEventType('entity_test', 'entity_test');
    $this->registrationType = $this->createRegistrationType();

    $event_name = $this->randomString();
    $event_meta = $this->createEvent([
      'name' => $event_name,
    ]);

    $registration = $this->createRegistration($event_meta->getEvent(), $this->registrationType->id());
    $user = $this->drupalCreateUser();
    $registration->addIdentity($user)->save();

    $registrant_ids = $registration->getRegistrantIds();
    $registrant_id = reset($registrant_ids);
    $this->registrant = Registrant::load($registrant_id);

    $field_name = mb_strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'registrant',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'registrant',
      'bundle' => 'registrant',
    ])->save();

    $form_display = \Drupal::service('entity_display.repository')->getFormDisplay('registrant', 'registrant', 'default');
    $form_display->setComponent($field_name, [
      'type' => 'text_textfield',
      'weight' => 1,
    ]);
    $form_display->save();

    $display = \Drupal::service('entity_display.repository')->getViewDisplay('registrant', 'registrant', 'default');
    $display->setComponent($field_name, [
      'type' => 'text_default',
      'weight' => 1,
    ]);
    $display->save();

    $this->registrant->{$field_name} = $this->randomMachineName();
    $this->registrant->save();

    $this->registrantTestField = $field_name;
  }

  /**
   * Test access registrant canonical route.
   */
  public function testRegistrantCanonicalRoute() {
    $admin = $this->drupalCreateUser(['administer rng']);
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('entity.registrant.canonical', [
      'registrant' => $this->registrant->id(),
    ]));
    $this->assertResponse(200);

    $test_field_value = $this->registrant->{$this->registrantTestField}->value;
    $this->assertRaw($test_field_value);

    // Breadcrumb.
    $this->assertLink(t('Home'));
    $this->assertLink($this->registrant->getRegistration()->getEvent()->label());
    $this->assertLink($this->registrant->getRegistration()->label());
  }

  /**
   * Test access registrant canonical route.
   */
  public function testRegistrantCanonicalNoAccess() {
    $admin = $this->drupalCreateUser();
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('entity.registrant.canonical', [
      'registrant' => $this->registrant->id(),
    ]));
    $this->assertResponse(403);
  }

  /**
   * Test access edit registrant form.
   */
  public function testRegistrantEditRoute() {
    $admin = $this->drupalCreateUser(['administer rng']);
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('entity.registrant.edit_form', [
      'registrant' => $this->registrant->id(),
    ]));
    $this->assertResponse(200);
    $this->assertFieldByName($this->registrantTestField . '[0][value]');

    // Breadcrumb.
    $this->assertLink(t('Home'));
    $this->assertLink($this->registrant->getRegistration()->getEvent()->label());
    $this->assertLink($this->registrant->getRegistration()->label());
    $this->assertLink($this->registrant->label());
  }

  /**
   * Test access edit registrant form with no permission.
   */
  public function testRegistrantEditRouteNoAccess() {
    $admin = $this->drupalCreateUser();
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('entity.registrant.edit_form', [
      'registrant' => $this->registrant->id(),
    ]));
    $this->assertResponse(403);
  }

  /**
   * Test access registrant delete form.
   */
  public function testRegistrantDeleteRoute() {
    $admin = $this->drupalCreateUser(['administer rng']);
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('entity.registrant.delete_form', [
      'registrant' => $this->registrant->id(),
    ]));
    $this->assertResponse(200);
    $this->assertText(t('Are you sure you want to delete this registrant?'));

    // Breadcrumb.
    $this->assertLink(t('Home'));
    $this->assertLink($this->registrant->getRegistration()->getEvent()->label());
    $this->assertLink($this->registrant->getRegistration()->label());
    $this->assertLink($this->registrant->label());
  }

  /**
   * Test access delete registrant form with no permission.
   */
  public function testRegistrantDeleteRouteNoAccess() {
    $admin = $this->drupalCreateUser();
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('entity.registrant.delete_form', [
      'registrant' => $this->registrant->id(),
    ]));
    $this->assertResponse(403);
  }

}
