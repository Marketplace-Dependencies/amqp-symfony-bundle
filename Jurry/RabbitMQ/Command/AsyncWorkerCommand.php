<?php
/**
 * User: Wajdi Jurry
 * Date: 22 May 2020
 * Time: 12:30 ص
 */

namespace Jurry\RabbitMQ\Command;


use Jurry\RabbitMQ\Handler\AmqpHandler;
use Jurry\RabbitMQ\Handler\RequestHandler;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AsyncWorkerCommand extends Command
{
    /** @var AmqpHandler */
    private $amqpHandler;

    /** @var LoggerInterface */
    private $logger;

    /** @var RequestHandler */
    private $requestHandler;

    /**
     * AsyncWorkerCommand constructor.
     * @param AmqpHandler $amqpHandler
     * @param LoggerInterface $logger
     * @param RequestHandler $requestHandler
     */
    public function __construct(AmqpHandler $amqpHandler, LoggerInterface $logger, RequestHandler $requestHandler)
    {
        $this->amqpHandler = $amqpHandler;
        $this->logger = $logger;
        $this->requestHandler = $requestHandler;

        parent::__construct('async_worker');
    }

    protected function configure()
    {
        $this->setDescription('Async Queue Worker Command');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->amqpHandler->declareAsync();

        $channel = $this->amqpHandler->getChannel();
        $properties = $this->amqpHandler->getProperties();

        try {
            $channel->basic_qos(null, 1, null);
            $channel->basic_consume($properties['async_queue']['name'], '', false, true, false, false,
                function (AMQPMessage $message) {
                    try {
                        $payload = json_decode($message->getBody(), true);
                        $this->requestHandler->process(
                            $payload['service'],
                            $payload['method'],
                            $payload['params']
                        );
                    } catch (\Throwable $exception) {
                        $this->logger->error($exception->getMessage());
                    }
                }
            );

            while (count($channel->callbacks)) {
                $channel->wait();
            }

        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        return 0;
    }
}