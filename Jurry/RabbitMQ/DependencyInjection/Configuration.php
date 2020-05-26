<?php
/**
 * User: Wajdi Jurry
 * Date: ٢٣‏/٥‏/٢٠٢٠
 * Time: ١٢:٥٩ ص
 */

namespace Jurry\RabbitMQ\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('amqp_handler');
        $rootNode = method_exists(TreeBuilder::class, 'getRootNode') ? $treeBuilder->getRootNode() : $treeBuilder->root('amqp_handler');

        $rootNode->children()
            ->scalarNode('connection')
                ->info('Connection parameters')
                ->validate()
                    ->ifEmpty()
                    ->thenInvalid('You should provide connection string')
                ->end()
                ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) {
                        $url = parse_url($v);
                        if (is_array($url)) {
                            $url['password'] = $url['pass'];
                            unset($url['scheme'], $url['pass']);
                        }

                        return ['connection' => $url];
                    })
                ->end()
            ->end()
            ->arrayNode('queues_properties')
                ->info('Queues properties')
                ->children()
                    ->arrayNode('sync_queue')
                        ->children()
                            ->scalarNode('name')->info('Sync queue name')->cannotBeEmpty()->end()
                            ->booleanNode('passive')->defaultFalse()->end()
                            ->booleanNode('durable')->defaultFalse()->end()
                            ->booleanNode('exclusive')->defaultFalse()->end()
                            ->booleanNode('auto_delete')->defaultFalse()->end()
                            ->booleanNode('no_wait')->defaultFalse()->end()
                            ->integerNode('message_ttl')->info('Message TTL')->defaultValue(10000)->end()
                        ->end()
                    ->end()
                    ->arrayNode('async_queue')
                        ->children()
                            ->scalarNode('name')->info('Sync queue name')->cannotBeEmpty()->end()
                            ->booleanNode('passive')->defaultFalse()->end()
                            ->booleanNode('durable')->defaultFalse()->end()
                            ->booleanNode('exclusive')->defaultFalse()->end()
                            ->booleanNode('auto_delete')->defaultFalse()->end()
                            ->booleanNode('no_wait')->defaultFalse()->end()
                            ->integerNode('message_ttl')->info('Message TTL')->defaultValue(10000)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}