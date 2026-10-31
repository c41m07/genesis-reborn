<?php
namespace App\Integration\Mercure;
interface PublisherInterface
{
    public function publish(string $topic,array $data,string $id): void;
}
