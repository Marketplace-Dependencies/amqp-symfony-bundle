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
  "route": "http://localhost:8000/path/to/your/route", # base route should be set as ENV variable
  "method": "GET|POST|DELETE|PUT|...etc",
  "body": "Request body",
  "query": "Query parameters",
  "headers": "Request headers"
}
```

Example: Assume service A asks service B for a random number
> Service A will send this message to service B:
> ```json
> {
>   "route": "http://localhost:8000/api/number",
>   "method": "POST",
>   "body": {"min": 1, "max": 10}
> }
> ```

Service B will receive the message, then try to consume it, so, it should have the route "POST /api/number" to return a response
```php
class NumberController {
    public function generate(Request $request)
    {
        return rand($request->get('min'), $request->get('max'));
    }
}
```

You have to set the base_uri as environment variable, just set these values in your `.env` file:
```bash
JURRY_BASE_API_URI=http://localhost:8000/api/
JURRY_HTTP_CLIENT_TIMEOUT=10
```

#### Request sender
In order to send requests to another queue, you can inject the `RequestSender` service inside any class you want, then:
```php
use Jurry\RabbitMQ\Handler\RequestSender;

class FooBar {
    /** @var \Jurry\RabbitMQ\Handler\RequestSender*/
    private $requestSender;
    
    public function __construct(RequestSender $requestSender)
    {
        $this->requestSender = $requestSender;
    }
    
    public function getRandomNumberFromAnotherApp(int $min = 1, int $max = 10): int
    {
        return $this->requestSender
            ->setQueueName('external_queue_name') // This is the queue name which another app listens to
            ->setRoute('number/generate') // The path to the requested route, without providing the full link
            ->setMethod('post') // Method name implemented inside the service class
            ->setBody(['min' => $min, 'max' => $max])
            ->sendSync();
    }
}
```
#### Change Log
1. Now, this bundle makes http request to the requested route instead of calling the service class directly. 
This makes it easier and fully functional of using controllers, this will help to use validation, middlewares and other functionalities implemented in the controller.

2. New environment values added:
```bash
JURRY_BASE_API_URI=http://localhost:8000/api/
JURRY_HTTP_CLIENT_TIMEOUT=10
```
These values will be used while initiating the http request inside the service itself