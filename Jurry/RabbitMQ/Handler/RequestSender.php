<?php
/**
 * User: Wajdi Jurry
 * Date: 16/02/19
 * Time: 06:27 م
 */

namespace Jurry\RabbitMQ\Handler;

use App\Exception\OperationFailed;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class RequestSender
{
    const REQUEST_TYPE_SYNC = 'sync';
    const REQUEST_TYPE_ASYNC = 'async';

    private $queueName;
    private $service;
    private $serviceArgs;
    private $method;
    private $correlationId;
    private $data;
    private $replyTo = null;
    private $exchange = null;
    private $requestType;

    /** @var AMQPChannel */
    private $channel;

    /** @var AMQPMessage */
    private $response;

    /** @var LoggerInterface */
    private $logger;

    /**
     * QueueRequestHandler constructor.
     * @param AmqpHandler $handler
     * @param LoggerInterface $logger
     */
    public function __construct(AmqpHandler $handler, LoggerInterface $logger)
    {
        $this->channel = $handler->getChannel();
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId = uniqid('', true);
    }

    /**
     * @param string $queueName
     * @return RequestSender
     */
    public function setQueueName(string $queueName)
    {
        $this->queueName = $queueName;

        return $this;
    }

    /**
     * @param string $service
     * @return RequestSender
     */
    public function setService(string $service)
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @param array $args
     * @return RequestSender
     */
    public function setServiceArgs(array $args = [])
    {
        $this->serviceArgs = $args;

        return $this;
    }

    /**
     * @param string $method
     * @return RequestSender
     */
    public function setMethod(string $method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param array $data
     * @return RequestSender
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param string $replyTo
     * @return RequestSender
     */
    public function setReplyTo(string $replyTo)
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * @param string $exchange
     * @return RequestSender
     */
    public function setExchange(string $exchange)
    {
        $this->exchange = $exchange;

        return $this;
    }

    /**
     * @throws OperationFailed
     */
    private function validate()
    {
        $validator = Validation::createValidator();

        $constraints = new Collection([
            'queueName' => [new NotBlank()],
            'service' => [new NotBlank()],
            'method' => [new NotBlank()]
        ]);

        $validatedFields = [
            'queueName' => $this->queueName,
            'service' => $this->service,
            'method' => $this->method
        ];

        $messages = $validator->validate($validatedFields, $constraints);

        if (!count($messages)) {
            return;
        }

        $errors = [];
        foreach ($messages as $message) {
            $errors[] = $message->getMessage();
        }

        if ($this->requestType == self::REQUEST_TYPE_ASYNC) {
            $this->logger->error(json_encode($errors));
        } elseif ($this->requestType == self::REQUEST_TYPE_SYNC) {
            throw new OperationFailed($errors, 400);
        } else {
            throw new \Exception('Unknown request type', 400);
        }
    }

    /**
     * Initialize consumer for Sync requests
     * @throws \Exception
     */
    private function initializeConsumer(): void
    {
        if (empty($this->replyTo)) {
            throw new \Exception('Property "reply_to" is missing');
        }
        $this->channel->basic_consume($this->replyTo, '', false, false, false, false, [
            $this,
            'getResponse'
        ]);
    }

    /**
     * Wait response for Sync requests
     * @throws \ErrorException
     */
    private function waitResponse(): void
    {
        while (!isset($this->response)) {
            $this->channel->wait(null, false, 10);
        }
    }

    /**
     * @param AMQPMessage $response
     * @throws \Exception
     */
    public function getResponse($response)
    {
        if ($response->get('correlation_id') == $this->correlationId) {
            $this->response = json_decode($response->getBody(), true);
            if (array_key_exists('hasError', $this->response) && true === $this->response['hasError']) {
                $this->channel->basic_ack($response->delivery_info['delivery_tag']);
                throw new \Exception($this->response['message'], $this->response['code']);
            }
        }

    }

    /**
     * Send sync request to another endpoint
     * and waiting response
     *
     * @return mixed
     *
     * @throws \ErrorException
     * @throws \Exception
     */
    public function sendSync()
    {
        $this->requestType = self::REQUEST_TYPE_SYNC;

        // validate request
        $this->validate();

        list($this->replyTo, ,) = $this->channel->queue_declare('', false, true, true, true);

        $this->initializeConsumer();
        $message = new AMQPMessage(json_encode([
            'service' => $this->service,
            'service_args' => $this->serviceArgs,
            'method' => $this->method,
            'params' => $this->data
        ]), [
            'reply_to' => $this->replyTo,
            'correlation_id' => $this->getCorrelationId(),
            'deliver_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);
        $this->channel->basic_publish($message, $this->exchange, $this->queueName);

        // Waiting response
        $this->waitResponse();

        // close connection
        $this->channel->close();

        // Return response
        return $this->response;
    }

    /**
     * @throws OperationFailed
     */
    public function sendAsync()
    {
        $this->requestType = self::REQUEST_TYPE_ASYNC;

        // validate request
        $this->validate();

        $message = new AMQPMessage(json_encode([
            'service' => $this->service,
            'service_args' => $this->serviceArgs,
            'method' => $this->method,
            'params' => $this->data
        ]));
        $this->channel->basic_publish($message, $this->exchange, $this->queueName);
        $this->channel->close();
    }
}