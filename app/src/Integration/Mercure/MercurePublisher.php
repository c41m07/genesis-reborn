<?php
namespace App\Integration\Mercure;
use Symfony\Component\Mercure\{HubInterface,Update};
final class MercurePublisher implements PublisherInterface
{
    public function __construct(private HubInterface $hub) {}
    public function publish(string $topic,array $data,string $id): void
    {
        $this->hub->publish(new Update($topic,json_encode($data,JSON_THROW_ON_ERROR),true,$id));
    }
}
