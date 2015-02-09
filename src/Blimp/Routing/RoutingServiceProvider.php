<?php
namespace Blimp\Routing;

use Blimp\Routing\HttpEventSubscriber as HttpEventSubscriber;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Config\Resource\FileResource;

class RoutingServiceProvider implements ServiceProviderInterface {
    public function register(Container $api) {
        $api['routing.config.dir'] = __DIR__;
        $api['routing.config.file'] = 'routes.yml';
        $api['routing.config.cache'] = __DIR__;

        $api['routing.config.locator'] = function ($api) {
            return new FileLocator(array($api['config.dir']));
        };

        $api['routing.routes'] = function ($api) {
            $yaml_loader = new YamlFileLoader($api['routing.config.locator']);

            $routes = $yaml_loader->load($api['routing.config.file']);

            return $routes;
        };

        $api['routing.utils.resolve'] = $api->protect(function($class, $type) use ($api) {
            if (false === strpos($class, '\\')) {
                foreach ($api['blimp.package_roots'] as $root) {
                    $parts = explode('::', $class);
                    $classname = array_pop($parts);

                    $classpath = $root . '\\' . implode('\\', $parts) . '\\' . $type . '\\' . $classname;

                    if(class_exists($classpath)) {
                        $class = $classpath;
                        break;
                    }
                }
            }

            return $class;
        });

        $api['routing.matcher'] = function ($api) {
            $cachePath = $api['routing.config.cache'] . '/' . $api['routing.config.file'] . '.php';

            $cache = new ConfigCache($cachePath, true);

            if (!$cache->isFresh()) {
                $routes = $api['routing.routes'];

                foreach ($routes as $value) {
                    $params = $value->getDefaults();

                    if(array_key_exists('_controller', $params)) {
                        if (false === strpos($params['_controller'], '\\')) {
                            $params['_controller'] = $api['routing.utils.resolve']($params['_controller'], 'Rest') . '::process';
                        }
                    }

                    if(array_key_exists('_resourceClass', $params)) {
                        $params['_resourceClass'] = $api['routing.utils.resolve']($params['_resourceClass'], 'Documents');
                    }

                    if(array_key_exists('_parentResourceClass', $params)) {
                        $params['_parentResourceClass'] = $api['routing.utils.resolve']($params['_parentResourceClass'], 'Documents');
                    }

                    $value->setDefaults($params);
                }

                $dumper = new PhpMatcherDumper($routes);

                $options = [
                    'class' => 'BlimpRoutesMatcher',
                    'base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
                ];

                $cache->write($dumper->dump($options), [new FileResource($api['routing.config.locator']->locate($api['routing.config.file']))]);
            }

            require_once $cache;

            return new \BlimpRoutesMatcher($api['routing.context']);
        };

        $api['routing.context'] = function ($api) {
            $context = new RequestContext();
            $context->fromRequest($api['http.request']);

            return $context;
        };

        $api['routing.url_generator'] = function ($api) {
            return new UrlGenerator($api['routing.routes'], $api['routing.context']);
        };

        $api['routing.listener'] = function ($api) {
            return new RouterListener($api['routing.matcher'], null, $api['blimp.logger']);
        };

        $api['routing.http.listener'] = function ($api) {
            return new HttpEventSubscriber($api);
        };

        $api->extend('blimp.init', function ($status, $api) {
            if($status) {
                $api['http.dispatcher']->addSubscriber($api['routing.listener']);
                $api['http.dispatcher']->addSubscriber($api['routing.http.listener']);
            }

            return $status;
        });
    }
}
