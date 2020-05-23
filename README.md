## RabbitMQ symfony bundle v1.0
#### Description:

Its purpose is to initiate workers (consumers) and to send "sync" and "async" requests to another queues or exchanges.
---

#### Installation

```bash
composer require jurry/amqp-symfony-bundle
```
---

#### Usage

After installing the bundle using composer, a new configuration file 
created `config/packages/jurry_rabbitmq.yaml`. Let's take a look:
```yaml
amqp_handler:
#    You can define connection in both ways: as array or as a string
#    connection: 'tcp://guest:guest@172.18.0.01:5672'
    connection:
        host: 172.18.0.1
        port: 5672
        user: guest
        password: guest
    queues_properties:
        sync_queue:
            name: stores_sync
            message_ttl: 10000 # 10 seconds
        async_queue:
            name: stores_async
            message_ttl: 10000 # 10 seconds
```
- As you see, you have a ready config yaml file, you can start editing these values as you need.
- The root attribute describes the service name, while the second attribute `connection` describes the connection string with RabbitMQ broker server.
    
> Note: the connection attribute could be defined in two ways:
> 1. Connection string, example: "tcp://guest:guest@172.18.0.01:5672"
> 2. Array definition
    
- The third and last attribute is `queues_properties`, which describes the config related to queues that should be defined once workers (consumers) started.

- Once you fit the config per your needs, you can start the workers (consumers) by running these commands:
```bash
./bin/console sync_worker # to start Sync consumer
./bin/console async_worker # to start Async consumer
```
- These two commands will create two queues for you, first one is "{name}_sync" and "{name}_async"

#### Workers (Consumers)
Workers (consumers) are looking for your registered services to initiate and execute the requested method inside them,
So when another application requests an info from yours, they should send in the message body the following data:
```json
{
  "service": "service_name",
  "method": "the method to be called inside the service class",
  "params": "the data to be passed to the method inside the service class"
}
```

Example: Assume service A asks service B for a random number
> Service A will send this message to service B:
> ```json
> {
>   "service": "random_number",
>   "method": "generate",
>   "params": {"min": 1, "max": 10}
> }
> ```

Service B will receive the message, then try to consume it, so, it should have the service class "RandomNumberService" with method "generate" implemented inside:
```php
class RandomNumberService {
    public function generate(int $min, int $max)
    {
        return rand($min, $max);
    }
}
```
This service should be registered as a public service in the container dependency injector, which you can achieve this by adding new definition inside `config/services.yaml`:
```yaml
...
app.random_number:
    class: App\Service\RandomNumberService
    public: true
```
As you see, the service id should be prefixed by `app.`, that's the way this package looking for registered services.
Same behavior applies for both "sync" and "async" workers.

#### Request sender
In order to send requests to another queue, you can inject the `RequestSender` service inside any class you want, then:
```php
class FooBar {
    /** @var \Jurry\RabbitMQ\Handler\RequestSender*/
    private $requestSender;
    
    public function __construct(\Jurry\RabbitMQ\Handler\RequestSender $requestSender)
    {
        $this->requestSender = $requestSender;
    }
    
    public function getRandomNumberFromAnotherApp(int $min = 1, int $max = 10): int
    {
        return $this->requestSender
            ->setQueueName('external_queue_name') // This is the queue name which another app listens to
            ->setService('randomNumber') // This is the service class name which you target (RandomNumberService)
            ->setMethod('generate') // Method name implemented inside the service class
            ->setData(['min' => $min, 'max' => $max])
            ->sendSync();
    }
}
```
If you're using this bundle within another Symfony app, you should follow the rules mentioned in "Workers (Consumers)" section in naming services classes, else, you're free to use any naming convention matches the another app definition for services.