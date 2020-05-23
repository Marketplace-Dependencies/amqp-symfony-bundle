<?php
/**
 * User: Wajdi Jurry
 * Date: ٢٣‏/٥‏/٢٠٢٠
 * Time: ٢:١٨ م
 */

namespace Jurry\RabbitMQ\Command;


use Jurry\RabbitMQ\Handler\AmqpHandler;
use Jurry\RabbitMQ\Handler\RequestHandler;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncWorkerCommand extends Command
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

        parent::__construct('sync_worker');
    }

    protected function configure()
    {
        $this->setDescription('Async Queue Worker Command');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->amqpHandler->declareSync();

        $channel = $this->amqpHandler->getChannel();
        $properties = $this->amqpHandler->getProperties();

        try {
            $channel->basic_qos(null, 1, null);
            $channel->basic_consume($properties['sync_queue']['name'],
                '', false, false, false, false,
                function (AMQPMessage $request) use ($channel) {
                    $payload = json_decode($request->getBody(), true);
                    /** @var AMQPChannel $amqpRequest */
                    $amqpRequest = $request->delivery_info['channel'];
                    try {

                        // handle request
                        $response = $this->requestHandler->process(
                            $payload['service'],
                            $payload['method'],
                            $payload['params']
                        );

                        // send response
                        $message = json_encode($response);

                    } catch (\Throwable $exception) {
                        $this->logger->error($exception->getMessage());
                        $message = json_encode([
                            'hasError' => true,
                            'message' => $exception->getMessage(),
                            'code' => $exception->getCode() ?: 500
                        ]);
                    }
                    $amqpRequest->basic_ack($request->delivery_info['delivery_tag']);
                    $amqpRequest->basic_publish(new AMQPMessage($message, [
                        'correlation_id' => $request->get('correlation_id'),
                        'reply_to' => $request->get('reply_to')
                    ]), '', $request->get('reply_to'));
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