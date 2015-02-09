<?php
namespace Blimp\Routing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class HttpEventSubscriber implements EventSubscriberInterface {
    private $api;

    public function __construct($api) {
        $this->api = $api;
    }

    public static function getSubscribedEvents() {
        return array(
            'kernel.exception' => array('onKernelException', 100)
        );
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {
        $e = $event->getException();

        if ($e instanceof ResourceNotFoundException) {
            $event->setException(new NotFoundHttpException($e->getMessage(), $e));
        }
    }
}
