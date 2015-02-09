<?php
namespace Blimp\Accounts;

use Pimple\ServiceProviderInterface;
use Pimple\Container;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class FoursquareServiceProvider implements ServiceProviderInterface {
    public function register(Container $api) {
        $api->extend('blimp.extend', function ($status, $api) {
            if($status) {
                if($api->offsetExists('config.root')) {
                    $api->extend('config.root', function ($root, $api) {
                        $tb = new TreeBuilder();

                        $rootNode = $tb->root('foursquare');

                        $rootNode
                            ->children()
                                ->scalarNode('client_id')->cannotBeEmpty()->end()
                                ->scalarNode('client_secret')->cannotBeEmpty()->end()
                                ->scalarNode('api_version')->defaultValue('20141111')->end()
                            ->end()
                        ;

                        $root->append($rootNode);

                        return $root;
                    });
                }
            }

            return $status;
        });
    }
}
