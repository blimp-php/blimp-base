<?php
namespace Blimp\Config;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/*
 * Based in: https://github.com/doctrine/DoctrineMongoDBBundle/blob/f01a2ac4ca695356d25562598e3400652f037cff/DependencyInjection/Configuration.php
*/
class BlimpConfig implements ConfigurationInterface {
    private $api;

    public function __construct($api) {
        $this->api = $api;
    }

    public function getConfigTreeBuilder() {
        return $this->api['config.builder'];
    }
}