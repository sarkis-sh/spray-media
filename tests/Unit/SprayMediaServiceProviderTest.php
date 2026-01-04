<?php

namespace SprayMedia\Tests\Unit;

use SprayMedia\Application\MediaItemManager;
use SprayMedia\Contracts\FileServerInterface;
use SprayMedia\Contracts\FileUploaderInterface;
use SprayMedia\Contracts\MediaItemRepositoryInterface;
use SprayMedia\Contracts\MediaItemUrlGeneratorInterface;
use SprayMedia\Contracts\PayloadValidatorInterface;
use SprayMedia\Contracts\ResponseAdapterInterface;
use SprayMedia\Infrastructure\Http\DefaultResponseAdapter;
use SprayMedia\Infrastructure\Persistence\EloquentMediaItemRepository;
use SprayMedia\Infrastructure\Serving\LocalFileServer;
use SprayMedia\Infrastructure\Storage\LocalFileUploader;
use SprayMedia\Infrastructure\Url\HmacMediaItemUrlGenerator;
use SprayMedia\Infrastructure\Validation\HmacPayloadValidator;
use SprayMedia\Tests\TestCase;

class SprayMediaServiceProviderTest extends TestCase
{
    public function test_default_bindings_resolve_concretes(): void
    {
        $this->assertInstanceOf(EloquentMediaItemRepository::class, $this->app->make(MediaItemRepositoryInterface::class));
        $this->assertInstanceOf(LocalFileUploader::class, $this->app->make(FileUploaderInterface::class));
        $this->assertInstanceOf(HmacMediaItemUrlGenerator::class, $this->app->make(MediaItemUrlGeneratorInterface::class));
        $this->assertInstanceOf(HmacPayloadValidator::class, $this->app->make(PayloadValidatorInterface::class));
        $this->assertInstanceOf(LocalFileServer::class, $this->app->make(FileServerInterface::class));
        $this->assertInstanceOf(DefaultResponseAdapter::class, $this->app->make(ResponseAdapterInterface::class));
    }

    public function test_singleton_bindings_share_instances(): void
    {
        $urlA = $this->app->make(MediaItemUrlGeneratorInterface::class);
        $urlB = $this->app->make(MediaItemUrlGeneratorInterface::class);
        $this->assertSame($urlA, $urlB);

        $validatorA = $this->app->make(PayloadValidatorInterface::class);
        $validatorB = $this->app->make(PayloadValidatorInterface::class);
        $this->assertSame($validatorA, $validatorB);

        $serverA = $this->app->make(FileServerInterface::class);
        $serverB = $this->app->make(FileServerInterface::class);
        $this->assertSame($serverA, $serverB);

        $responderA = $this->app->make(ResponseAdapterInterface::class);
        $responderB = $this->app->make(ResponseAdapterInterface::class);
        $this->assertSame($responderA, $responderB);
    }

    public function test_alias_resolves_media_manager(): void
    {
        $this->assertInstanceOf(MediaItemManager::class, $this->app->make('spray-media'));
    }
}
