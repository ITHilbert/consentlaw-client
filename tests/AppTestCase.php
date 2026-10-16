<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\ViewServiceProvider;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\ConsentLawServiceProvider;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Basis für alle Tests, die den echten Ablauf des Packages brauchen.
 *
 * Es gibt kein orchestra/testbench. Deshalb wird hier eine echte, aber kleine
 * Laravel-Application aufgebaut (Cache = ArrayStore, Filesystem = Temp-Verzeichnis,
 * View/Blade, Translator, Log = TestHandler) und der ConsentLawServiceProvider
 * registriert. Dadurch laufen die Tests gegen die echte Verdrahtung (Config →
 * HttpTransport → ContentResolver → Endpoints → Blade-Komponenten).
 *
 * Nur das HTTP wird ersetzt: Der Laravel-HTTP-Client (`Illuminate\Http\Client\Factory`)
 * ist ein Fake, dessen Antworten pro Test über {@see api()} festgelegt werden. Der
 * echte HttpTransport bleibt im Pfad – so wird auch die Übersetzung von
 * HTTP-Status/Verbindungsfehler in Exceptions mitgetestet (AK1–AK3, AK6).
 *
 * Zeit: Carbon::setTestNow, vorgespult über {@see reise()}. Cache (ArrayStore)
 * und Sperrvermerke folgen der Test-Uhr.
 *
 * Konfiguration: Wer {@see konfig()} nutzt, muss es VOR dem ersten Zugriff auf den
 * Client tun (der ContentResolver ist ein Singleton und liest die Config beim Bau).
 */
abstract class AppTestCase extends TestCase
{
    /** Pseudo-Antwort: Timeout / Verbindungsfehler (keine HTTP-Antwort). */
    protected const STOERUNG = 'stoerung';

    protected const TOKEN = 'test-token';

    /** Cache-TTL aus der Standard-Config (Sekunden). */
    protected const CACHE_TTL = 3600;

    protected Application $app;

    protected HttpFactory $http;

    protected string $basis;

    /** Fängt alle Log-Einträge des Tests ab (siehe {@see logText()}). */
    protected TestHandler $logHandler;

    /** URLs aller HTTP-Aufrufe, die der Client ausgelöst hat (auch fehlgeschlagene). @var list<string> */
    protected array $aufrufe = [];

    /** @var array<string, mixed> */
    private array $routen = [];

    /** @var array<string, string|false> */
    private array $umgebung = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->umgebungLeeren();

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-16 12:00:00'));

        $this->baueApp('testing', []);
    }

    /**
     * Baut die kleine Laravel-App samt Provider auf.
     *
     * Routen, Limiter und Config-Schalter werden beim BOOTEN des Providers
     * ausgewertet. Tests, die davon abhängen (z. B. der Neu-laden-Button), müssen
     * Umgebung und Config deshalb VOR dem Boot setzen – siehe {@see appNeuBauen()}.
     *
     * @param  array<string, mixed>  $konfig  Werte für `consentlaw.<schluessel>`, gesetzt vor dem Provider-Boot
     */
    private function baueApp(string $umgebung, array $konfig): void
    {
        $this->aufrufe = [];

        $this->basis = sys_get_temp_dir().'/consentlaw-client-test-'.bin2hex(random_bytes(6));

        foreach (['storage/framework/views', 'storage/app', 'views'] as $ordner) {
            mkdir($this->basis.'/'.$ordner, 0777, true);
        }

        // Application::getNamespace() liest die composer.json des Basispfads.
        file_put_contents($this->basis.'/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ]));

        $this->app = new Application($this->basis);

        // Wie der Kernel einer echten App: ohne diese Bindung wirft app()->environment().
        $this->app->detectEnvironment(static fn (): string => $umgebung);

        $this->app->instance('config', new ConfigRepository([
            'app' => [
                'name' => 'Test',
                'locale' => 'de',
                'fallback_locale' => 'de',
                'key' => 'base64:'.base64_encode(str_repeat('a', 32)),
                'cipher' => 'AES-256-CBC',
            ],
            'view' => ['paths' => [$this->basis.'/views'], 'compiled' => $this->basis.'/storage/framework/views'],
            'cache' => [
                'default' => 'array',
                'stores' => ['array' => ['driver' => 'array', 'serialize' => false]],
                'prefix' => '',
            ],
            'filesystems' => [
                'default' => 'local',
                'disks' => ['local' => ['driver' => 'local', 'root' => $this->basis.'/storage/app']],
            ],
            'logging' => [
                'default' => 'test',
                'channels' => ['test' => ['driver' => 'monolog', 'handler' => TestHandler::class]],
            ],
            'session' => [
                'driver' => 'array',
                'lifetime' => 120,
                'expire_on_close' => false,
                'encrypt' => false,
                'files' => $this->basis.'/storage/framework/sessions',
                'connection' => null,
                'table' => 'sessions',
                'store' => null,
                'lottery' => [0, 100],
                'cookie' => 'consentlaw_test_session',
                'path' => '/',
                'domain' => null,
                'secure' => false,
                'http_only' => true,
                'same_site' => 'lax',
                'partitioned' => false,
            ],
        ]));

        foreach ($konfig as $schluessel => $wert) {
            $this->konfig($schluessel, $wert);
        }

        // Der Log-Manager baut den Handler mit Parametern (level) über den Container –
        // ein instance()-Binding würde dabei umgangen. Die Closure liefert immer dieselbe Instanz.
        $this->logHandler = new TestHandler;
        $this->app->bind(TestHandler::class, fn (): TestHandler => $this->logHandler);

        $this->http = new HttpFactory;
        $this->http->fake(fn (Request $request) => $this->beantworte($request));
        $this->app->instance(HttpFactory::class, $this->http);

        // Die Facades müssen VOR dem Boot auf diese App zeigen: Der Provider darf
        // beim Booten Route/RateLimiter nutzen. Der Request wird für UrlGenerator
        // und Redirector gebraucht (echte Apps binden ihn im Kernel).
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->app->instance('request', HttpRequest::create('http://localhost/'));

        $this->app->register(FilesystemServiceProvider::class);
        $this->app->register(CacheServiceProvider::class);
        $this->app->register(ViewServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
        $this->app->register(EncryptionServiceProvider::class);
        $this->app->register(SessionServiceProvider::class);

        // Wie in einer echten App: Middleware-Gruppe `web` und Alias `throttle`
        // (in Laravel kommen sie aus dem Kernel bzw. bootstrap/app.php).
        $router = $this->app['router'];
        $router->middlewareGroup('web', [
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);
        $router->aliasMiddleware('throttle', ThrottleRequests::class);

        $this->app->register(ConsentLawServiceProvider::class);
        $this->app->boot();

        // Wie RouteServiceProvider::booted() in einer echten App.
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();

        // Alles aus der Package-Config bleibt Standard (Test von Standardwerten!),
        // nur Token und Warte-/Retry-Zeiten des HTTP-Clients werden test-tauglich gesetzt.
        $this->konfig('token', self::TOKEN);
        $this->konfig('http.retries', 0);
        $this->konfig('http.retry_delay', 0);
    }

    /**
     * Verwirft die App und baut sie mit anderer Umgebung/Config neu auf.
     *
     * Nötig für alles, was der Provider beim Booten entscheidet (Routen, Limiter).
     * Danach {@see api()} neu setzen ist nicht nötig, die Fake-Antworten bleiben.
     *
     * @param  string  $umgebung  z. B. 'local', 'testing', 'production'
     * @param  array<string, mixed>  $konfig  z. B. ['refresh_button' => true]
     */
    protected function appNeuBauen(string $umgebung, array $konfig = []): void
    {
        $this->verwerfeApp();
        $this->baueApp($umgebung, $konfig);
    }

    private function verwerfeApp(): void
    {
        // Blade-Komponenten halten statisch die View-Factory des ersten Tests fest.
        Component::flushCache();
        Component::forgetFactory();
        Component::forgetComponentsResolver();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        (new Filesystem)->deleteDirectory($this->basis);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->verwerfeApp();

        $this->umgebungWiederherstellen();

        parent::tearDown();
    }

    // ── Konfiguration ────────────────────────────────────────────────────────

    /** Setzt `consentlaw.<schluessel>`. Nur vor dem ersten Client-Zugriff wirksam. */
    protected function konfig(string $schluessel, mixed $wert): void
    {
        $this->app['config']->set('consentlaw.'.$schluessel, $wert);
    }

    protected function client(): ConsentLawClient
    {
        return $this->app->make(ConsentLawClient::class);
    }

    // ── Fake-API ─────────────────────────────────────────────────────────────

    /**
     * Legt fest, wie die consentLaw-API antwortet.
     *
     * Schlüssel = Teilstring der URL (z. B. '/api/agb/'), '*' = alles andere.
     * Der erste passende Teilstring gewinnt, '*' gilt zuletzt. Ohne Treffer und
     * ohne '*' gilt eine Störung.
     *
     * Wert:
     *  - array          → Status 200 mit diesem JSON
     *  - int            → Fehlerstatus ohne verwertbaren Inhalt (404, 429, 503 …)
     *  - self::STOERUNG → Verbindungsfehler/Timeout (keine Antwort)
     *  - Closure        → liefert einen der obigen Werte (Argument: Request)
     *
     * @param  array<string, mixed>  $routen
     */
    protected function api(array $routen): void
    {
        $this->routen = $routen;
    }

    private function beantworte(Request $request): mixed
    {
        $this->aufrufe[] = $request->url();

        $antwort = null;
        $gefunden = false;

        foreach ($this->routen as $muster => $wert) {
            if ($muster !== '*' && str_contains($request->url(), $muster)) {
                $antwort = $wert;
                $gefunden = true;

                break;
            }
        }

        if (! $gefunden) {
            $antwort = $this->routen['*'] ?? self::STOERUNG;
        }

        if ($antwort instanceof Closure) {
            $antwort = $antwort($request);
        }

        if ($antwort === self::STOERUNG) {
            // Wie echtes cURL/Guzzle: Die Meldung nennt die vollständige URL (samt Token im Pfad).
            throw new ConnectException(
                'cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received for '.$request->url(),
                new PsrRequest('GET', $request->url()),
            );
        }

        if (is_int($antwort)) {
            return HttpFactory::response(['message' => 'Fehler'], $antwort);
        }

        return HttpFactory::response($antwort, 200);
    }

    /** @return array<string, mixed> */
    protected function dokumentPayload(string $content, string $format = 'markdown'): array
    {
        return [
            'content' => $content,
            'format' => $format,
            'generated_at' => '2026-10-01T10:00:00+00:00',
            'locale' => 'de',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{jobs: list<array<string, mixed>>}
     */
    protected function jobsPayload(array $overrides = []): array
    {
        return ['jobs' => [array_merge([
            'id' => 'job-1',
            'title' => 'PHP-Entwickler',
            'description' => 'Wir suchen Verstärkung.',
            'description_format' => 'markdown',
            'location' => 'Kiel',
            'employment_type' => 'full_time',
            'employment_type_label' => 'Vollzeit',
            'employment_type_schema' => 'FULL_TIME',
            'date_posted' => '2026-09-01T10:00:00+00:00',
            'valid_through' => null,
        ], $overrides)]];
    }

    protected function anzahlAufrufe(): int
    {
        return count($this->aufrufe);
    }

    // ── Log ──────────────────────────────────────────────────────────────────

    /**
     * Alle bisherigen Log-Einträge als ein Text: Level, Meldung und Kontext
     * (Exceptions mit Klasse und Meldung, Objekte als Text bzw. JSON).
     */
    protected function logText(): string
    {
        $zeilen = [];

        foreach ($this->logHandler->getRecords() as $eintrag) {
            $zeilen[] = $eintrag->level->getName().' '.$eintrag->message.' '
                .json_encode($this->logNormalisiert($eintrag->context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                .' '.json_encode($this->logNormalisiert($eintrag->extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return implode("\n", $zeilen);
    }

    protected function anzahlLogEintraege(): int
    {
        return count($this->logHandler->getRecords());
    }

    private function logNormalisiert(mixed $wert): mixed
    {
        if (is_array($wert)) {
            return array_map(fn (mixed $teil): mixed => $this->logNormalisiert($teil), $wert);
        }

        if ($wert instanceof \Throwable) {
            return get_class($wert).': '.$wert->getMessage().($wert->getPrevious() ? ' <- '.$this->logNormalisiert($wert->getPrevious()) : '');
        }

        if ($wert instanceof \Stringable) {
            return (string) $wert;
        }

        return is_object($wert) ? get_object_vars($wert) : $wert;
    }

    // ── Zeit ─────────────────────────────────────────────────────────────────

    /** Spult die Test-Uhr um $sekunden vor (Cache-Ablauf, Wartezeit). */
    protected function reise(int $sekunden): void
    {
        Carbon::setTestNow(CarbonImmutable::now()->addSeconds($sekunden));
    }

    /** Lässt den frischen Cache ablaufen, ohne die Wartezeit nennenswert zu berühren. */
    protected function cacheAblaufenLassen(): void
    {
        $this->reise(self::CACHE_TTL + 1);
    }

    // ── Fallback-Dateien ─────────────────────────────────────────────────────

    /** @return list<string> */
    protected function fallbackDateien(): array
    {
        return array_values(glob($this->basis.'/storage/app/consentlaw/*.json') ?: []);
    }

    // ── Rendering & Commands ─────────────────────────────────────────────────

    /** Rendert ein Blade-Template (z. B. `<x-consentlaw::impressum />`). */
    protected function rendere(string $blade): string
    {
        return Blade::render($blade);
    }

    /**
     * Führt einen Artisan-Command des Packages aus.
     *
     * @param  class-string<Command>  $commandClass
     * @param  array<string, mixed>  $eingabe
     * @return array{0: int, 1: string} Exit-Code und Ausgabe
     */
    protected function fuehreAus(string $commandClass, array $eingabe = []): array
    {
        /** @var Command $command */
        $command = $this->app->make($commandClass);
        $command->setLaravel($this->app);

        $ausgabe = new BufferedOutput;
        $code = $command->run(new ArrayInput($eingabe), $ausgabe);

        return [$code, $ausgabe->fetch()];
    }

    // ── Umgebungsvariablen (CONSENTLAW_*) ────────────────────────────────────

    /** Standardwerte sollen nicht von der Entwickler-Umgebung abhängen. */
    private function umgebungLeeren(): void
    {
        $namen = array_filter(
            array_unique([...array_keys(getenv()), ...array_keys($_ENV), ...array_keys($_SERVER)]),
            static fn (string|int $name): bool => is_string($name) && str_starts_with($name, 'CONSENTLAW_'),
        );

        foreach ($namen as $name) {
            $this->umgebung[$name] = getenv($name);
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    private function umgebungWiederherstellen(): void
    {
        foreach ($this->umgebung as $name => $wert) {
            putenv($wert === false ? $name : "{$name}={$wert}");
        }

        $this->umgebung = [];
    }

    /** Setzt eine Umgebungsvariable für diesen Test (wird in tearDown entfernt). */
    protected function setzeUmgebung(string $name, string $wert): void
    {
        $this->umgebung[$name] ??= getenv($name);
        putenv("{$name}={$wert}");
    }
}
