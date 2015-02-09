<?php
namespace Blimp\Http;

use Blimp\Http\HttpEventSubscriber as HttpEventSubscriber;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpKernel;

class HttpServiceProvider implements ServiceProviderInterface {
    public function register(Container $api) {
        $api['http.dispatcher'] = function ($api) {
            return new EventDispatcher();
        };

        $api['http.request'] = function () {
            return Request::createFromGlobals();
        };

        $api['http.resolver'] = function ($api) {
            return new HttpControllerResolver($api, $api['blimp.logger']);
        };

        $api['http.request_stack'] = function () {
            return new RequestStack();
        };

        $api['http.kernel'] = function ($api) {
            return new HttpKernel($api['http.dispatcher'], $api['http.resolver'], $api['http.request_stack']);
        };

        $api['http.listener'] = function ($api) {
            return new HttpEventSubscriber($api);
        };

        $api['http.session'] = function () {
            return new Session();
        };

        $api['http.process'] = $api->protect(function () use ($api) {
            $request = $api['http.request'];

            $response = $api['http.kernel']->handle($request);

            $response->prepare($request);

            $response->send();

            $api['http.kernel']->terminate($request, $response);
        });

        $api->extend('blimp.init', function ($status, $api) {
            if ($status) {
                $api['http.dispatcher']->addSubscriber($api['http.listener']);
            }

            return $status;
        });

        $api->extend('blimp.process', function ($results, $api) {
            $results[] = $api['http.process']();

            return $results;
        });
    }
}
