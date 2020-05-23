<?php
/**
 * User: Wajdi Jurry
 * Date: 22/02/19
 * Time: 04:40 م
 */

namespace Jurry\RabbitMQ\Handler;


use Psr\Container\ContainerInterface;

class RequestHandler
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $service
     * @return string
     */
    private function formatServiceName(string $service): string
    {
        return sprintf('app.%s_service', $service);
    }

    /**
     * @param string $service
     * @param string $method
     * @param $params
     * @return mixed
     *
     * @throws \Exception
     */
    public function process(string $service, string $method, $params)
    {
        $service = $this->container->get($this->formatServiceName($service));

        if (!is_callable([$service, $method])) {
            throw new \Exception('Method "' . get_class($service) . '::' . $method . '" is not a callable method');
        }

        return call_user_func_array([$service, $method], $params);
    }
}
