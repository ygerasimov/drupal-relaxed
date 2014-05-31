<?php

namespace Drupal\couch_api\Plugin\rest\resource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\couch_api\ResourceManagerInterface;
use Drupal\rest\Plugin\ResourceBase as CoreResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class ResourceBase extends CoreResourceBase {

  public function routes() {
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $api_root = trim(\Drupal::config('couch_api.settings')->get('api_root'), '/');
    $route_name = strtr($this->pluginId, ':', '.');

    foreach ($this->availableMethods() as $method) {
      // HEAD and GET are equivalent as per RFC and handled by the same route.
      // @see \Symfony\Component\Routing\Matcher::matchCollection()
      if ($method == 'HEAD') {
        continue;
      }

      $method_lower = strtolower($method);
      $route = new Route($api_root . $definition['uri_paths']['canonical'], array(
        '_controller' => 'Drupal\couch_api\Controller\ResourceController::handle',
        '_plugin' => $this->pluginId,
      ), array(
        '_method' => $method,
        '_permission' => "restful " . $method_lower . " $this->pluginId",
      ), array(
        '_access_mode' => 'ANY',
      ));

      if (isset($definition['uri_paths'][$method_lower])) {
        $route->setPattern($definition['uri_paths'][$method_lower]);
      }

      if (isset($definition['uri_parameters'][$method_lower])) {
        $route->addOptions(array('parameters' => $definition['uri_parameters'][$method_lower]));
      }
      elseif (isset($definition['uri_parameters']['canonical'])) {
        $route->addOptions(array('parameters' => $definition['uri_parameters']['canonical']));
      }

      switch ($method) {
        case 'POST':
        case 'PUT':
          // Restrict on the Content-Type header.
          $route->addRequirements(array('_content_type_format' => implode('|', $this->serializerFormats)));
          $collection->add("$route_name.$method", $route);
          break;

        case 'GET':
          // Restrict on the Accept header.
          foreach ($this->serializerFormats as $format) {
            $format_route = clone $route;
            $format_route->addRequirements(array('_format' => $format));
            $collection->add("$route_name.$method.$format", $format_route);
          }
          break;

        default:
          $collection->add("$route_name.$method", $route);
          break;
      }
    }
    return $collection;
  }

  protected function validate(ContentEntityInterface $entity) {
    $violations = $entity->validate();
    if (count($violations) > 0) {
      $messages = array();
      foreach ($violations as $violation) {
        $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
      }
      throw new BadRequestHttpException(implode('. ', $messages));
    }
  }
}
