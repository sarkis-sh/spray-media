<?php

namespace SprayMedia\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler as LaravelExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler as FoundationExceptionHandler;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use SprayMedia\Application\MediaItemManager;
use SprayMedia\Contracts\FileServerInterface;
use SprayMedia\Contracts\FileUploaderInterface;
use SprayMedia\Contracts\MediaItemRepositoryInterface;
use SprayMedia\Contracts\PayloadValidatorInterface;
use SprayMedia\Contracts\ResponseAdapterInterface;
use SprayMedia\Contracts\MediaItemUrlGeneratorInterface;
use SprayMedia\Domain\Exceptions\MediaException;
use SprayMedia\Infrastructure\Http\DefaultResponseAdapter;
use SprayMedia\Infrastructure\Persistence\EloquentMediaItemRepository;
use SprayMedia\Infrastructure\Serving\LocalFileServer;
use SprayMedia\Infrastructure\Storage\LocalFileUploader;
use SprayMedia\Infrastructure\Url\HmacMediaItemUrlGenerator;
use SprayMedia\Infrastructure\Validation\HmacPayloadValidator;
class SprayMediaServiceProvider extends ServiceProvider
{

    /**
     * The key to use for the configuration file.
     * @var string
     */
    private const CONFIG_KEY = 'spray-media';

    /**
     * Register any application services.
     *
     * This method is used for binding services into the service container.
     * It should not be used to register any event listeners, routes, or
     * any other piece of functionality.
     *
     * @return void
     */
    public function register(): void
    {
        // 1. Merge the package's default configuration with the user's published version.
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/media.php',
            self::CONFIG_KEY
        );

        // 2. Bind the interfaces to their concrete implementations (config overridable).
        $bindings = [
            MediaItemRepositoryInterface::class => ['config' => 'bindings.repository', 'default' => EloquentMediaItemRepository::class],
            FileUploaderInterface::class => ['config' => 'bindings.uploader', 'default' => LocalFileUploader::class],
            MediaItemUrlGeneratorInterface::class => ['config' => 'bindings.url_generator', 'default' => HmacMediaItemUrlGenerator::class, 'singleton' => true],
            PayloadValidatorInterface::class => ['config' => 'bindings.payload_validator', 'default' => HmacPayloadValidator::class, 'singleton' => true],
            FileServerInterface::class => ['config' => 'bindings.file_server', 'default' => LocalFileServer::class, 'singleton' => true],
            ResponseAdapterInterface::class => ['config' => 'bindings.response_adapter', 'default' => DefaultResponseAdapter::class, 'singleton' => true],
        ];

        foreach ($bindings as $contract => $meta) {
            $concrete = Config::get(self::CONFIG_KEY . '.' . $meta['config'], $meta['default']);
            $method = ($meta['singleton'] ?? false) ? 'singleton' : 'bind';
            $this->app->{$method}($contract, $concrete);
        }

        $this->app->alias(MediaItemManager::class, 'spray-media');
    }

    /**
     * Bootstrap any application services.
     *
     * This method is called after all other service providers have been registered,
     * meaning you have access to all other services that have been registered
     * by the framework.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }

        // 1. Register the package's routes.
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // 2. Register the package's database migrations.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // 3. Register translations.
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'spray-media');

        // 4. Register exception rendering for package exceptions.
        $this->registerExceptionHandler();
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/media.php' => $this->app->configPath(self::CONFIG_KEY . '.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../Database/Migrations' => $this->app->databasePath('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/spray-media'),
        ], 'lang');
    }

    protected function registerExceptionHandler(): void
    {
        /** @var \Illuminate\Foundation\Exceptions\Handler|LaravelExceptionHandler $handler */
        $handler = $this->app->make(LaravelExceptionHandler::class);

        if ($handler instanceof FoundationExceptionHandler && method_exists($handler, 'renderable')) {
            $handler->renderable(function (MediaException $e, $request) {
                $responder = $this->app->make(ResponseAdapterInterface::class);

                return $responder->error(
                    Lang::get($e->getMessageKey()),
                    $e->getStatus()
                );
            });

            $handler->renderable(function (ValidationException $e, $request) {
                $responder = $this->app->make(ResponseAdapterInterface::class);
                $errorList = array_map(function (array $messages) {
                    return implode(', ', $messages);
                }, $e->errors());

                return $responder->error(
                    Lang::get('spray-media::messages.bad_request'),
                    Response::HTTP_BAD_REQUEST,
                    $errorList
                );
            });
        }
    }
}
