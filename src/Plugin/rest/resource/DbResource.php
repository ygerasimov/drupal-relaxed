<?php

namespace Drupal\couch_api\Plugin\rest\resource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\multiversion\Entity\RepositoryInterface;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * @RestResource(
 *   id = "couch:root:db",
 *   label = "Couch database",
 *   serialization_class = {
 *     "canonical" = "Drupal\multiversion\Entity\RepositoryInterface",
 *     "post" = "Drupal\Core\Entity\ContentEntityInterface",
 *   },
 *   uri_paths = {
 *     "canonical" = "/{db}",
 *   },
 *   uri_parameters = {
 *     "canonical" = {
 *       "db" = {
 *         "type" = "entity_uuid:repository",
 *       }
 *     }
 *   }
 * )
 */
class DbResource extends ResourceBase {

  /**
   * @param string | \Drupal\multiversion\Entity\RepositoryInterface $entity
   */
  public function head($entity) {
    if (!$entity instanceof RepositoryInterface) {
      throw new NotFoundHttpException();
    }
    return new ResourceResponse(NULL, 200);
  }

  /**
   * @param string | \Drupal\multiversion\Entity\RepositoryInterface $entity
   */
  public function get($entity) {
    if (!$entity instanceof RepositoryInterface) {
      throw new NotFoundHttpException();
    }
    // @todo: Access check.
    return new ResourceResponse($entity, 200);
  }

  /**
   * @param string | \Drupal\multiversion\Entity\RepositoryInterface $name
   */
  public function put($name) {
    // If the name parameter was upcasted to an entity it means it an entity
    // already exists.
    if ($name instanceof RepositoryInterface) {
      throw new PreconditionFailedHttpException(t('The database could not be created, it already exists'));
    }
    elseif (!is_string($name)) {
      throw new BadRequestHttpException(t('Database name is missing'));
    }

    try {
      // @todo Consider using the container injected in parent::create()
      $entity = \Drupal::service('entity.manager')
        ->getStorage('repository')
        ->create(array('name' => $name))
        ->save();
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, t('Internal server error'), $e);
    }
    return new ResourceResponse(array('ok' => TRUE), 201);
  }

  /**
   * @param string | \Drupal\multiversion\Entity\RepositoryInterface $repository
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   */
  public function post($repository, ContentEntityInterface $entity = NULL) {
    // If the repository parameter is a string it means it could not be upcasted
    // to an entity because none exiisted.
    if (is_string($repository)) {
      throw new NotFoundHttpException(t('Database does not exist')); 
    }
    elseif (empty($entity)) {
      throw new BadRequestHttpException(t('No content received'));
    }

    // Check for conflicts.
    if (!empty($entity->uuid())) {
      $entry = \Drupal::service('entity.uuid_index')->get($entity->uuid());
      if (!empty($entry)) {
        throw new ConflictHttpException();
      }
    }

    // Check entity and field level access.
    if (!$entity->access('create')) {
      throw new AccessDeniedHttpException();
    }
    foreach ($entity as $field_name => $field) {
      if (!$field->access('create')) {
        throw new AccessDeniedHttpException(t('Access denied on creating field @field.', array('@field' => $field_name)));
      }
    }

    // Validate the received data before saving.
    $this->validate($entity);
    try {
      $entity->save();
      $rev = $entity->_revs_info->rev;
      return new ResourceResponse(array('ok' => TRUE, 'id' => $entity->uuid(), 'rev' => $rev), 201, array('ETag' => $rev));
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, NULL, $e);
    }
  }

  /**
   * @param \Drupal\multiversion\Entity\RepositoryInterface $entity
   */
  public function delete(RepositoryInterface $entity) {
    try {
      // @todo: Access check.
      $entity->delete();
    }
    catch (\Exception $e) {
      throw new HttpException(500, NULL, $e);
    }
    return new ResourceResponse(array('ok' => TRUE), 200);
  }
}
