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
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\ViewServiceProvider;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\ConsentLawServiceProvider;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Basis für alle Tests, die den echten Ablauf des Packages brauchen.
 *
 * Es gibt kein orchestra/testbench. Deshalb wird hier eine echte, aber kleine
 * Laravel-Application aufgebaut (Cache = ArrayStore, Filesystem = Temp-Verzeichnis,
 * View/Blade, Translator, Log = NullHandler) und der ConsentLawServiceProvider
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

        $this->basis = sys_get_temp_dir().'/consentlaw-client-test-'.bin2hex(random_bytes(6));

        foreach (['storage/framework/views', 'storage/app', 'views'] as $ordner) {
            mkdir($this->basis.'/'.$ordner, 0777, true);
        }

        // Application::getNamespace() liest die composer.json des Basispfads.
        file_put_contents($this->basis.'/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ]));

        $this->app = new Application($this->basis);

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
                'default' => 'null',
                'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]],
            ],
        ]));

        $this->http = new HttpFactory;
        $this->http->fake(fn (Request $request) => $this->beantworte($request));
        $this->app->instance(HttpFactory::class, $this->http);

        $this->app->register(FilesystemServiceProvider::class);
        $this->app->register(CacheServiceProvider::class);
        $this->app->register(ViewServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
        $this->app->register(ConsentLawServiceProvider::class);
        $this->app->boot();

        Facade::setFacadeApplication($this->app);

        // Alles aus der Package-Config bleibt Standard (Test von Standardwerten!),
        // nur Token und Warte-/Retry-Zeiten des HTTP-Clients werden test-tauglich gesetzt.
        $this->konfig('token', self::TOKEN);
        $this->konfig('http.retries', 0);
        $this->konfig('http.retry_delay', 0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        // Blade-Komponenten halten statisch die View-Factory des ersten Tests fest.
        Component::flushCache();
        Component::forgetFactory();
        Component::forgetComponentsResolver();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        (new Filesystem)->deleteDirectory($this->basis);

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
            throw new ConnectException('cURL error 28: Operation timed out', new PsrRequest('GET', $request->url()));
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
