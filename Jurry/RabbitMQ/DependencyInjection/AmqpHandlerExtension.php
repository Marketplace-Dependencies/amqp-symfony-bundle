<?php
/**
 * User: Wajdi Jurry
 * Date: ٢٣‏/٥‏/٢٠٢٠
 * Time: ١٢:٢٤ ص
 */

namespace Jurry\RabbitMQ\DependencyInjection;


use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class AmqpHandlerExtension extends Extension
{

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @return mixed
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $connection = $container->resolveEnvPlaceholders($config['connection'], true);
        $connection = parse_url($connection);

        $definition = $container->getDefinition('jurry.rabbit_mq.handler.amqp_handler');
        $definition->setArgument(0, $connection['host']);
        $definition->setArgument(1, $connection['port']);
        $definition->setArgument(2, $connection['user']);
        $definition->setArgument(3, $connection['pass']);
        $definition->setArgument(4, $config['queues_properties']);
    }
}