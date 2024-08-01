<?php

namespace Drupal\rng\Tests;

use Drupal\rng\Entity\RngEventType;
use Drupal\courier\CourierContextInterface;
use Drupal\courier\Entity\CourierContext;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Url;

/**
 * Tests event types.
 *
 * @group rng
 */
class RngEventTypeTest extends RngWebTestBase {

  public static $modules = ['node', 'field_ui', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Test event types in UI.
   */
  public function testEventType() {
    $web_user = $this->drupalCreateUser(['administer event types', 'access administration pages']);
    $this->drupalLogin($web_user);

    // Create and delete the testing event type.
    $event_bundle = $this->drupalCreateContentType();
    $event_type = $this->createEventType('node', $event_bundle->id());
    $this->drupalGet('admin/structure/rng/event_types/manage/' . $event_type->id() . '/edit');
    $event_type->delete();
    $event_bundle->delete();

    // Admin structure overview.
    $this->drupalGet('admin/structure');
    $this->assertLinkByHref(Url::fromRoute('rng.structure')->toString());
    $this->assertSession()->responseContains('Manage registration entity types.');

    // Event types button on admin.
    $this->drupalGet('admin/structure/rng');
    $this->assertLinkByHref(Url::fromRoute('rng.rng_event_type.overview')->toString());
    $this->assertSession()->responseContains('Manage which entity bundles are designated as events.');

    // No events.
    $this->assertEqual(0, count(RngEventType::loadMultiple()), 'There are no event type entities.');
    $this->drupalGet('admin/structure/rng/event_types');
    $this->assertSession()->responseContains('No event types found.');

    // There are no courier contexts.
    $this->assertEqual(0, count(CourierContext::loadMultiple()), 'There are no courier context entities.');

    // Local action.
    $this->assertLinkByHref(Url::fromRoute('entity.rng_event_type.add')->toString());

    // Add.
    $t_args = ['%label' => 'node.event'];
    $edit = [
      'registrants[registrant_type]' => 'registrant',
    ];
    $this->drupalPostForm('admin/structure/rng/event_types/add', $edit, t('Save'));

    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::load('event');

    $this->assertEqual(1, count(RngEventType::loadMultiple()), 'Event type exists in database.');

    $this->assertSession()->responseContains(t('The content type <a href=":url">%label</a> has been added.', [
      '%label' => $node_type->label(),
      ':url' => $node_type->toUrl()->toString(),
    ]));
    $this->assertSession()->responseContains(t('%label event type added.', $t_args));

    // Assert that a Courier context entity was created.
    $this->assertInstanceof(CourierContextInterface::class, CourierContext::load('rng_registration_node'), 'Courier context entity created for this event type\' entity type.');

    // Event type list.
    $this->drupalGet('admin/structure/rng/event_types');
    $this->assertSession()->responseContains('<td>Content: Event</td>');
    $options = ['node_type' => 'event'];
    $this->assertLinkByHref(Url::fromRoute("entity.node.field_ui_fields", $options)->toString());

    // Edit form.
    $edit = [];
    $this->drupalPostForm('admin/structure/rng/event_types/manage/node.event/edit', $edit, t('Save'));
    $this->assertSession()->responseContains(t('%label event type updated.', $t_args));

    // Delete form.
    $this->drupalGet('admin/structure/rng/event_types/manage/node.event/delete');
    $this->assertSession()->responseContains('Are you sure you want to delete event type node.event?');

    $this->drupalPostForm('admin/structure/rng/event_types/manage/node.event/delete', [], t('Delete'));
    $this->assertSession()->responseContains(t('Event type %label was deleted.', $t_args));

    $this->assertEqual(0, count(RngEventType::loadMultiple()), 'Event type deleted from database.');

    // @todo: ensure conditional on form omits node/existing radios
    // @todo create event type with custom entity
  }

}
