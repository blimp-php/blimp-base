<?php
namespace Blimp\Base;

use Pimple\Container;
use Psr\Log\NullLogger;

class BlimpAPI extends Container {
    public function __construct($package_roots = [], $logger = null, $debug = false) {
        parent::__construct();

        if ($logger == null) {
            $logger = new NullLogger();
        }

        $this['blimp.logger'] = $logger;

        $this['blimp.debug'] = $debug;

        $this['blimp.package_roots'] = array_merge(['Blimp'], $package_roots);

        $this['config'] = function () {
            return [];
        };

        $this['blimp.extend'] = function () {
            return true;
        };

        $this['blimp.init'] = function () {
            return true;
        };

        $this['blimp.process'] = function () {
            return [];
        };
    }

    public function process() {
        if ($this['blimp.extend']) {
            if ($this['blimp.init']) {
                $results = $this['blimp.process'];
            }
        }
    }
}
