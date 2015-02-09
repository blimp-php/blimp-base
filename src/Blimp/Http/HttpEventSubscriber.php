<?php
namespace Blimp\Http;

use Blimp\Base\BlimpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class HttpEventSubscriber implements EventSubscriberInterface {
    private $api;

    public function __construct($api) {
        $this->api = $api;
    }

    public static function getSubscribedEvents() {
        return array(
            'kernel.request' => array('onKernelRequest', 0),
            'kernel.controller' => array('onKernelController', 0),
            'kernel.view' => array('onKernelView', 0),
            'kernel.response' => array('onKernelResponse', 0),
            'kernel.exception' => array('onKernelException', -500),
        );
    }

    public function onKernelRequest(GetResponseEvent $event) {
        $request = $event->getRequest();

        $data = null;
        $content_type = $request->headers->get('Content-Type');
        if (!empty($content_type)) {
            if (strpos($content_type, 'application/json') === 0) {
                $data = json_decode($request->getContent(), true);
            } else if (strpos($content_type, 'application/x-www-form-urlencoded') === 0) {
                $data = $request->request->all();
            } else if (strpos($content_type, 'multipart/form-data') === 0) {
                $data = $request->request->all();
            }
        } else if($request->getMethod() === 'GET' || $request->getMethod() === 'HEAD') {
            $data = $request->query->all();
        }

        $request->attributes->set('data', $data);
    }

    public function onKernelController(FilterControllerEvent $event) {
        $request = $event->getRequest();

        if($request->getMethod() == 'OPTIONS') {
            $event->setController(function() {
                return new Response();
            });
        }
    }

    public function onKernelView(GetResponseForControllerResultEvent $event) {
        $response = new JsonResponse();
        $response->setData($event->getControllerResult());
        $event->setResponse($response);
    }

    public function onKernelResponse(FilterResponseEvent $event) {
        $response = $event->getResponse();

        $this->addCorsHeaders($event->getRequest(), $response);

        $headers = $response->headers;

        if (!$headers->has('Content-Length') && !$headers->has('Transfer-Encoding')) {
            $headers->set('Content-Length', strlen($response->getContent()));
        }
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {
        $e = $event->getException();

        if ($e instanceof BlimpException) {
            $response = new JsonResponse($e, $e->getStatusCode(), $e->getHeaders());
        } else if ($e instanceof HttpExceptionInterface) {
            $response = new JsonResponse(["error" => $e->getMessage(), "code" => $e->getStatusCode()], $e->getStatusCode(), $e->getHeaders());
        } else {
            $response = new JsonResponse(["error" => $e->getMessage(), "stack" => array_slice($e->getTrace(), 0, 3), "code" => Response::HTTP_INTERNAL_SERVER_ERROR], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->addCorsHeaders($event->getRequest(), $response);

        $headers = $response->headers;

        if (!$headers->has('Content-Length') && !$headers->has('Transfer-Encoding')) {
            $headers->set('Content-Length', strlen($response->getContent()));
        }

        $event->setResponse($response);
    }

    private function addCorsHeaders(Request $request, Response $response) {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        // $response->headers->set('Access-Control-Expose-Headers', '');

        if($request->getMethod() == 'OPTIONS') {
            $response->headers->set('Access-Control-Max-Age', '1728000');
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
            $response->headers->set('Access-Control-Allow-Methods', 'HEAD, GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Cache-Control');
        }
    }
}
