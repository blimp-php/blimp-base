<?php
namespace Blimp\Http;

use Pimple\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

class HttpControllerResolver extends ControllerResolver {
    protected $api;

    public function __construct(Container $api, LoggerInterface $logger = null) {
        parent::__construct($logger);

        $this->api = $api;
    }

    public function getController(Request $request) {
        return parent::getController($request);
    }

    protected function doGetArguments(Request $request, $controller, array $parameters) {
        foreach ($parameters as $param) {
            if ($param->getClass() && $param->getClass()->isInstance($this->api)) {
                $request->attributes->set($param->getName(), $this->api);
                break;
            }
        }

        return parent::doGetArguments($request, $controller, $parameters);
    }
}
