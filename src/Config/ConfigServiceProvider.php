<?php
namespace Blimp\Config;

use Blimp\Config\BlimpConfig as BlimpConfig;
use Blimp\Config\BlimpConfigLoader as BlimpConfigLoader;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;

class ConfigServiceProvider implements ServiceProviderInterface {
    public function register(Container $api) {
        $api['config.dir'] = __DIR__;
        $api['config.cache'] = function ($api) {
            return $api['config.dir'];
        };
        $api['config.file'] = 'config.yml';

        $api['config.locator'] = function ($api) {
            return new FileLocator(array($api['config.dir']));
        };

        $api['config.interface'] = function ($api) {
            return new BlimpConfig($api);
        };

        $api['config._builder'] = function () {
            return new TreeBuilder();
        };

        $api['config.root'] = function ($api) {
            return $api['config._builder']->root('config');
        };

        $api['config.builder'] = function ($api) {
            $root = $api['config.root'];
            return $api['config._builder'];
        };

        $api['config.file.path'] = function ($api) {
            return $api['config.locator']->locate($api['config.file']);
        };

        $api['config.cache.prepare'] = function ($api) {
            $loader = new BlimpConfigLoader($api['config.locator']);
            $configValues = $loader->load($api['config.file.path']);

            $processor = new Processor();
            $config = $processor->processConfiguration(
                $api['config.interface'],
                $configValues
            );

            return $config;
        };

        $api['config'] = function ($api) {
            $cachePath = $api['config.cache'] . '/' . $api['config.file'] . '.php';

            $cache = new ConfigCache($cachePath, true);

            $config = [];

            if (!$cache->isFresh()) {
                $config = $api['config.cache.prepare'];

                $code = "<?php return " . var_export($config, true) . ";";

                $cache->write($code, [new FileResource($api['config.file.path'])]);
            } else {
                $config = include $cachePath;
            }

            return $config;
        };
    }
}