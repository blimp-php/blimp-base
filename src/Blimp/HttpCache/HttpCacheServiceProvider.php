<?php
namespace Blimp\HttpCache;

use Pimple\ServiceProviderInterface;
use Pimple\Container;
use Symfony\Component\HttpKernel\EventListener\EsiListener;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;

class HttpCacheServiceProvider implements ServiceProviderInterface {
    public function register(Container $api) {
        $api['http_cache.cache'] = __DIR__;
        $api['http_cache.options'] = array();

        $api['http_cache.store'] = function ($api) {
            return new Store($api['http_cache.cache']);
        };

        $api['http_cache.esi'] = function ($api) {
            return new Esi();
        };

        $api['http_cache.listener'] = function ($api) {
            return new EsiListener($api['http_cache.esi']);
        };

        $api->extend('blimp.extend', function ($status, $api) {
            if($status) {
                $api->extend('http.kernel', function ($kernel, $api) {
                    return new HttpCache($kernel, $api['http_cache.store'], $api['http_cache.esi'], $api['http_cache.options']);
                });
            }

            return $status;
        });

        $api->extend('blimp.init', function ($status, $api) {
            if($status) {
                $api['http.dispatcher']->addSubscriber($api['http_cache.listener']);
            }

            return $status;
        });
    }
}
