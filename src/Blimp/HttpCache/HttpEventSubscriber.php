<?php
namespace Blimp\HttpCache;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\LogoutException;

class HttpEventSubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents() {
        return array(
            'kernel.request' => array('onKernelRequest', 0),
            'kernel.controller' => array('onKernelController', 0),
            'kernel.view' => array('onKernelView', 0),
            'kernel.exception' => array('onKernelException', 0)
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
            } else {
                throw new BlimpException(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
            }
        }

        if ($data != null) {
            unset($data['id']);
            unset($data['_id']);
        }

        $request->attributes->set('data', $data);
    }

    public function onKernelController(FilterControllerEvent $event) {
        $controller = $event->getController();
        $controller[0]->setAPI($this);
    }

    public function onKernelView(GetResponseForControllerResultEvent $event) {
        $response = new JsonResponse();
        $response->setData($event->getControllerResult());
        $event->setResponse($response);
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {
        $e = $event->getException();

        if ($e instanceof BlimpException) {
            $event->setResponse(new JsonResponse($e, $e->getCode()));
        } else if ($e instanceof ResourceNotFoundException) {
            $event->setResponse(new JsonResponse(["error" => 'Resource not found', "description" => $e->getMessage(), "code" => Response::HTTP_NOT_FOUND], Response::HTTP_NOT_FOUND));
        } else if ($e instanceof NotFoundHttpException) {
            $event->setResponse(new JsonResponse(["error" => 'Resource not found', "description" => $e->getMessage(), "code" => Response::HTTP_NOT_FOUND], Response::HTTP_NOT_FOUND));
        } else if ($e instanceof AuthenticationException) {
            $event->setResponse(new JsonResponse(["error" => 'Unauthorized', "description" => $e->getMessage(), "code" => Response::HTTP_UNAUTHORIZED], Response::HTTP_UNAUTHORIZED));
        } else if ($e instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse(["error" => 'Unauthorized', "description" => $e->getMessage(), "code" => Response::HTTP_UNAUTHORIZED], Response::HTTP_UNAUTHORIZED));
        } else if ($e instanceof LogoutException) {
            $event->setResponse(new JsonResponse(["error" => 'Unauthorized', "description" => $e->getMessage(), "code" => Response::HTTP_UNAUTHORIZED], Response::HTTP_UNAUTHORIZED));
        } else {
            $event->setResponse(new JsonResponse(["error" => $e->getMessage(), "code" => Response::HTTP_INTERNAL_SERVER_ERROR], Response::HTTP_INTERNAL_SERVER_ERROR));
        }
    }
}