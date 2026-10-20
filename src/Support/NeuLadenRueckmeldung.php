<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use ITHilbert\ConsentLaw\Data\Abrufergebnis;
use ITHilbert\ConsentLaw\Data\Abrufstatus;

/**
 * Rückmeldung nach „Neu laden“: aus dem Ergebnis wird ein Flash in der Session,
 * das genau einen Seitenaufruf lang bei der betroffenen Komponente (Typ + Sprache) angezeigt wird.
 *
 * In der Session liegen nur feste Kennungen und der Stand – keine Exception-Texte, URLs oder Token.
 */
final class NeuLadenRueckmeldung
{
    public const SESSION_KEY = 'consentlaw.neu_laden';

    private const KEIN_TOKEN = 'kein_token';

    private const ERFOLG = 'erfolg';

    private const STOERUNG_MIT_STAND = 'stoerung_mit_stand';

    private const STOERUNG_OHNE_STAND = 'stoerung_ohne_stand';

    private const NICHT_VEROEFFENTLICHT = 'nicht_veroeffentlicht';

    /** @return array{art: string, stand: ?string} */
    public function aus(Abrufergebnis $ergebnis): array
    {
        $stand = $ergebnis->stand?->toIso8601String();

        return match ($ergebnis->status) {
            Abrufstatus::Aktuell => ['art' => self::ERFOLG, 'stand' => $stand ?? CarbonImmutable::now()->toIso8601String()],
            Abrufstatus::Fallback => $stand === null
                ? ['art' => self::STOERUNG_OHNE_STAND, 'stand' => null]
                : ['art' => self::STOERUNG_MIT_STAND, 'stand' => $stand],
            Abrufstatus::NichtVeroeffentlicht => ['art' => self::NICHT_VEROEFFENTLICHT, 'stand' => null],
            Abrufstatus::Stoerung => ['art' => self::STOERUNG_OHNE_STAND, 'stand' => null],
        };
    }

    /** @return array{art: string, stand: ?string} */
    public function keinToken(): array
    {
        return ['art' => self::KEIN_TOKEN, 'stand' => null];
    }

    /**
     * Legt die Rückmeldung als Flash ab – nur, wenn der Request eine Session hat.
     *
     * @param  array{art: string, stand: ?string}  $rueckmeldung
     */
    public function ablegen(Request $request, string $typ, ?string $locale, array $rueckmeldung): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->flash(self::SESSION_KEY, [
            'typ' => $typ,
            'locale' => $locale,
            ...$rueckmeldung,
        ]);
    }

    /**
     * Die Meldung für die Komponente mit diesem Typ und dieser Sprache, sonst null.
     *
     * @return array{text: string, ok: bool}|null
     */
    public function fuer(Request $request, string $typ, ?string $locale): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $daten = $request->session()->get(self::SESSION_KEY);

        if (! is_array($daten) || ($daten['typ'] ?? null) !== $typ
            || (($daten['locale'] ?? null) ?: null) !== ($locale ?: null)) {
            return null;
        }

        $stand = $this->formatiere($daten['stand'] ?? null);

        return match ($daten['art'] ?? null) {
            self::ERFOLG => ['text' => __('Neu geladen – Stand: :stand', ['stand' => $stand]), 'ok' => true],
            self::STOERUNG_MIT_STAND => ['text' => __('Neu laden fehlgeschlagen: consentLaw nicht erreichbar. Angezeigt wird der Stand vom :stand.', ['stand' => $stand]), 'ok' => false],
            self::STOERUNG_OHNE_STAND => ['text' => __('Neu laden fehlgeschlagen: consentLaw nicht erreichbar, kein gespeicherter Stand vorhanden.'), 'ok' => false],
            self::NICHT_VEROEFFENTLICHT => ['text' => __('Neu laden fehlgeschlagen: Inhalt bei consentLaw nicht veröffentlicht oder Token ungültig.'), 'ok' => false],
            self::KEIN_TOKEN => ['text' => __('Neu laden fehlgeschlagen: kein Token konfiguriert (CONSENTLAW_TOKEN).'), 'ok' => false],
            default => null,
        };
    }

    private function formatiere(mixed $stand): string
    {
        if (! is_string($stand) || $stand === '') {
            return '';
        }

        return CarbonImmutable::parse($stand)
            ->setTimezone(config('app.timezone') ?: date_default_timezone_get())
            ->format('d.m.Y H:i');
    }
}
