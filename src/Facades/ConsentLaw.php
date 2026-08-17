<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Facades;

use Illuminate\Support\Facades\Facade;
use ITHilbert\ConsentLaw\ConsentLawClient;

/**
 * @method static \ITHilbert\ConsentLaw\ConsentLawClient locale(?string $locale)
 * @method static \ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint impressum()
 * @method static \ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint datenschutz()
 * @method static \ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint agb()
 * @method static \ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint cookieRichtlinie()
 * @method static \ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint avv()
 * @method static \ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint document(string $type)
 * @method static \ITHilbert\ConsentLaw\Endpoints\JobsEndpoint jobs()
 * @method static bool isConfigured()
 * @method static void flush()
 *
 * @see ConsentLawClient
 */
final class ConsentLaw extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConsentLawClient::class;
    }
}
