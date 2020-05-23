## RabbitMQ symfony bundle v1.0
#### Description:

- Its purpose is to initiate workers (consumers) and to send "sync" and "async" requests to another queues or exchanges.
---

#### Installation

```bash
composer require jurry/amqp-symfony-bundle
```
---

#### Usage

After installing the bundle using composer, a new configuration file 
created ```config/packages/jurry_rabbitmq.yaml```. Let's take a look:
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
