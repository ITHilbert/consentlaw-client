<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ITHilbert\ConsentLaw\Benachrichtigung\MeldeStoerungAnAlerts;
use ITHilbert\ConsentLaw\Console\ConsentLawFlushCommand;
use ITHilbert\ConsentLaw\Console\ConsentLawLoadCommand;
use ITHilbert\ConsentLaw\Contracts\FallbackStore;
use ITHilbert\ConsentLaw\Contracts\Transport;
use ITHilbert\ConsentLaw\Events\NotstandVeraltet;
use ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar;
use ITHilbert\ConsentLaw\Events\TokenVermutlichUngueltig;
use ITHilbert\ConsentLaw\Support\ContentResolver;
use ITHilbert\ConsentLaw\Support\FileFallbackStore;
use ITHilbert\ConsentLaw\Support\HttpTransport;

class ConsentLawServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/consentlaw.php', 'consentlaw');

        $this->app->singleton(Transport::class, function ($app): Transport {
            $config = $app['config']->get('consentlaw');

            return new HttpTransport(
                http: $app->make(HttpFactory::class),
                baseUrl: (string) $config['base_url'],
                timeout: (int) $config['http']['timeout'],
                connectTimeout: (int) $config['http']['connect_timeout'],
                retries: (int) $config['http']['retries'],
                retryDelay: (int) $config['http']['retry_delay'],
            );
        });

        $this->app->singleton(FallbackStore::class, function ($app): FallbackStore {
            $config = $app['config']->get('consentlaw.fallback');

            return new FileFallbackStore(
                disk: $app->make(FilesystemFactory::class)->disk($config['disk']),
                path: (string) $config['path'],
            );
        });

        $this->app->singleton(ContentResolver::class, function ($app): ContentResolver {
            $config = $app['config']->get('consentlaw');

            return new ContentResolver(
                transport: $app->make(Transport::class),
                cache: $app->make(CacheFactory::class)->store($config['cache']['store']),
                fallback: $app->make(FallbackStore::class),
                events: $app->make(Dispatcher::class),
                cacheEnabled: (bool) $config['cache']['enabled'],
                ttl: (int) $config['cache']['ttl'],
                cachePrefix: (string) $config['cache']['prefix'],
                fallbackEnabled: (bool) $config['fallback']['enabled'],
                warnAfter: (int) $config['fallback']['warn_after'],
                // ?? : ältere, bereits veröffentlichte Configs kennen den Schlüssel noch nicht.
                retryAfter: (int) ($config['fallback']['retry_after'] ?? 300),
            );
        });

        $this->app->singleton(ConsentLawClient::class, function ($app): ConsentLawClient {
            $config = $app['config']->get('consentlaw');

            return new ConsentLawClient(
                resolver: $app->make(ContentResolver::class),
                fallback: $app->make(FallbackStore::class),
                token: $config['token'] ?? null,
                locale: $config['locale'] ?? null,
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'consentlaw');

        $this->registerOptionalRoutes();
        $this->registerNeuLadenRoute();
        $this->registerStoerungsMeldung();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsentLawFlushCommand::class,
                ConsentLawLoadCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/Config/consentlaw.php' => config_path('consentlaw.php'),
            ], 'consentlaw-config');

            $this->publishes([
                __DIR__.'/Resources/views' => resource_path('views/vendor/consentlaw'),
            ], 'consentlaw-views');
        }
    }

    /**
     * Registriert fertige Seiten – nur wenn im Kundenprojekt ausdrücklich
     * aktiviert. Standard ist AUS, damit das Package keine URLs beansprucht
     * und nicht mit bestehenden Seiten kollidiert.
     */
    private function registerOptionalRoutes(): void
    {
        $config = $this->app['config']->get('consentlaw.routes');

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        Route::middleware($config['middleware'] ?? ['web'])
            ->prefix($config['prefix'] ?? '')
            ->group(function () use ($config): void {
                foreach (($config['paths'] ?? []) as $type => $path) {
                    if (! is_string($path) || $path === '') {
                        continue;
                    }

                    Route::get($path, [Http\ConsentLawPageController::class, 'show'])
                        ->defaults('consentlawType', $type)
                        ->name("consentlaw.{$type}");
                }
            });
    }

    /**
     * Hängt den Listener, der die drei Störungs-Events an `ithilbert/alerts` meldet, immer an.
     */
    private function registerStoerungsMeldung(): void
    {
        $this->app->make(Dispatcher::class)->listen(
            [RechtstextNichtLieferbar::class, TokenVermutlichUngueltig::class, NotstandVeraltet::class],
            MeldeStoerungAnAlerts::class,
        );
    }

    /**
     * Registriert Route und Rate-Limiter des „Neu laden“-Buttons immer. Die Freigabe
     * (Schalter `APP_TEST`, siehe NeuLadenFreigabe) prüfen nur Aktion und Partial: Ein
     * Route-Cache friert den Stand ein, ein schalterabhängig fehlender Limiter wäre ein 500.
     */
    private function registerNeuLadenRoute(): void
    {
        RateLimiter::for('consentlaw-neu-laden', static fn (Request $request): Limit => Limit::perMinute(6)->by((string) $request->ip()));

        Route::middleware(['web', 'throttle:consentlaw-neu-laden'])
            ->post('consentlaw/neu-laden/{typ}', Http\NeuLadenController::class)
            ->name('consentlaw.neu-laden');
    }
}
