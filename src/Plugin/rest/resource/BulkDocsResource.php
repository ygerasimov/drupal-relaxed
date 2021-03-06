<?php

/**
 * @file
 * Contains \Drupal\relaxed\Plugin\rest\resource\BulkDocsResource.
 */

namespace Drupal\relaxed\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @RestResource(
 *   id = "relaxed:bulk_docs",
 *   label = "Bulk documents",
 *   serialization_class = {
 *     "canonical" = "Drupal\multiversion\Entity\WorkspaceInterface",
 *     "post" = "Drupal\Core\Entity\ContentEntityInterface",
 *   },
 *   uri_paths = {
 *     "canonical" = "/{db}/_bulk_docs",
 *   }
 * )
 */
class BulkDocsResource extends ResourceBase {

  public function post($workspace, array $entities = array()) {
    $result = array();

    if  (empty($entities)) {
      throw new BadRequestHttpException(t('No content info received'));
    }

    foreach ($entities['docs'] as $entity) {
      // Validate the received data before saving.
      $this->validate($entity);
      try {
        $entity->save();
        $rev = $entity->_revs_info->rev;
        $result[] = array(
          'ok' => TRUE,
          'id' => $entity->uuid(),
          'rev' => $rev
        );
      }
      catch (EntityStorageException $e) {
        throw new HttpException(500, NULL, $e);
      }
    }

    return new ResourceResponse($result, 201);
  }
}
