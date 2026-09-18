<?php

namespace App\Services\Connectors;

use App\Contracts\NewsSourceConnector;
use App\Models\NewsSource;
use App\Support\SourceLead;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;

/**
 * Reads a time series from the Banco Central do Brasil SGS API (Selic,
 * cambio, IPCA, credito, ...). Config per NewsSource:
 *   - codigo_serie: SGS series code (ex: 433 = IPCA mensal, 11 = Selic)
 *   - ultimos: how many recent data points to fetch (ex: 3)
 */
class BcbSgsConnector implements NewsSourceConnector
{
    public function __construct(private readonly HttpFactory $http) {}

    public function fetch(NewsSource $source): Collection
    {
        $config = $source->config ?? [];
        $codigoSerie = $config['codigo_serie'] ?? 433;
        $ultimos = $config['ultimos'] ?? 3;

        $url = $source->endpoint
            ?: "https://api.bcb.gov.br/dados/serie/bcdata.sgs.{$codigoSerie}/dados/ultimos/{$ultimos}";

        $response = $this->http->timeout(20)->retry(2, 500)->get($url, ['formato' => 'json']);
        $response->throw();

        return collect((array) $response->json())->map(function (array $ponto) use ($source, $codigoSerie) {
            return new SourceLead(
                externalId: "{$source->key}:{$codigoSerie}:{$ponto['data']}",
                title: "{$source->name} em {$ponto['data']}",
                summary: "Valor registrado: {$ponto['valor']} (serie SGS {$codigoSerie}, data {$ponto['data']}).",
                referenceUrl: 'https://www3.bcb.gov.br/sgspub/',
                referenceTitle: 'Banco Central do Brasil - Sistema Gerenciador de Series Temporais (SGS)',
                facts: [
                    'serie' => $codigoSerie,
                    'data' => $ponto['data'],
                    'valor' => $ponto['valor'],
                ],
            );
        });
    }
}
