<?php

namespace Drupal\relaxed\Tests\Normalizer;

use Drupal\Core\Language\Language;
use Drupal\Component\Utility\String;
use Drupal\serialization\Tests\NormalizerTestBase;

/**
 * Tests the content serialization format.
 *
 * @group relaxed
 */
class ContentEntityNormalizerTest extends NormalizerTestBase {

  public static $modules = array('serialization', 'system', 'entity', 'field', 'entity_test', 'text', 'filter', 'user', 'key_value', 'multiversion', 'rest', 'uuid', 'relaxed');

  protected $entityClass = 'Drupal\entity_test\Entity\EntityTest';

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  protected function setUp() {
    parent::setUp();
    $this->installSchema('key_value', array('key_value_sorted'));

    $this->container
      ->get('entity.definition_update_manager')
      ->applyUpdates();

    // @todo: Attach a file field once multiversion supports attachments.

    // Create a test entity to serialize.
    $this->values = array(
      'name' => $this->randomMachineName(),
      'user_id' => 0,
      'field_test_text' => array(
        'value' => $this->randomMachineName(),
        'format' => 'full_html',
      ),
    );
    $this->entity = entity_create('entity_test_mulrev', $this->values);
    $this->entity->save();

    $this->serializer = $this->container->get('serializer');
  }

  public function testNormalize() {
    $entity = entity_load('entity_test_mulrev', 1);

    $expected = array(
      'id' => array(
        array('value' => 1),
      ),
      'revision_id' => array(
        array('value' => 1),
      ),
      'uuid' => array(
        array('value' => $this->entity->uuid()),
      ),
      'langcode' => array(
        array('value' => 'en'),
      ),
      'name' => array(
        array('value' => $this->values['name']),
      ),
      'type' => array(
        array('value' => 'entity_test_mulrev'),
      ),
      'user_id' => array(
        array('target_id' => $this->values['user_id']),
      ),
      'field_test_text' => array(
        array(
          'value' => $this->values['field_test_text']['value'],
          'format' => $this->values['field_test_text']['format'],
        ),
      ),
      '_local' => array(
        array(
          'value' => $entity->_local->first()->get('value')->getCastedValue(),
        )
      ),
      '_id' => $entity->uuid(),
      '_rev' => $entity->_revs_info->first()->get('rev')->getCastedValue(),
      '_deleted' => $entity->_deleted->first()->get('value')->getCastedValue(),
      '_local_seq' => $entity->_local_seq->first()->get('value')->getCastedValue(),
      '_entity_type' => $entity->getEntityTypeId(),
    );

    $normalized = $this->serializer->normalize($this->entity);

    foreach (array_keys($expected) as $key) {
      $this->assertEqual($expected[$key], $normalized[$key], "Field $key is normalized correctly.");
    }
    $this->assertEqual(array_diff_key($normalized, $expected), array(), 'No unexpected data is added to the normalized array.');

    // @todo Test context switches.
  }

  public function testSerialize() {
    $normalized = $this->serializer->normalize($this->entity);
    $expected = json_encode($normalized);
    // Paranoid test because JSON serialization is tested elsewhere.
    $actual = $this->serializer->serialize($this->entity, 'json');
    $this->assertIdentical($actual, $expected, 'Entity serializes correctly to JSON.');
  }

  public function testDenormalize() {
    $normalized = $this->serializer->normalize($this->entity);
    $denormalized = $this->serializer->denormalize($normalized, $this->entityClass, 'json');
    $this->assertTrue($denormalized instanceof $this->entityClass, String::format('Denormalized entity is an instance of @class', array('@class' => $this->entityClass)));
    $this->assertIdentical($denormalized->getEntityTypeId(), $this->entity->getEntityTypeId(), 'Expected entity type found.');
    $this->assertIdentical($denormalized->bundle(), $this->entity->bundle(), 'Expected entity bundle found.');
    $this->assertIdentical($denormalized->uuid(), $this->entity->uuid(), 'Expected entity UUID found.');

    // @todo Test context switches.
  }
}
