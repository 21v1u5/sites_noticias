<?php

namespace App\Services\Connectors;

use App\Contracts\NewsSourceConnector;
use App\Models\NewsSource;
use App\Support\SourceLead;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;

/**
 * Reads indicator series from the IBGE Agregados/SIDRA API (PIB, IPCA,
 * desemprego, populacao, ...). Config per NewsSource:
 *   - tabela: agregado/tabela ID (ex: 1737 = IPCA)
 *   - variavel: variable ID within the table (ex: 63 = variacao mensal)
 *   - localidades: locality filter (ex: "N1[all]" = Brasil)
 *   - periodos: how many recent periods to fetch (ex: -3)
 */
class IbgeSidraConnector implements NewsSourceConnector
{
    public function __construct(private readonly HttpFactory $http) {}

    public function fetch(NewsSource $source): Collection
    {
        $config = $source->config ?? [];
        $tabela = $config['tabela'] ?? '1737';
        $variavel = $config['variavel'] ?? '63';
        $localidades = $config['localidades'] ?? 'N1[all]';
        $periodos = $config['periodos'] ?? -3;

        $url = $source->endpoint
            ?: "https://servicodados.ibge.gov.br/api/v3/agregados/{$tabela}/periodos/{$periodos}/variaveis/{$variavel}";

        $response = $this->http->timeout(20)->retry(2, 500)->get($url, [
            'localidades' => $localidades,
        ]);
        $response->throw();

        $leads = collect();

        foreach ((array) $response->json() as $variableBlock) {
            $variableName = $variableBlock['variavel'] ?? $source->name;
            $unidade = $variableBlock['unidade'] ?? '';

            foreach ($variableBlock['resultados'] ?? [] as $resultado) {
                foreach ($resultado['series'] ?? [] as $serie) {
                    $localidade = $serie['localidade']['nome'] ?? 'Brasil';

                    foreach ($serie['serie'] ?? [] as $periodo => $valor) {
                        if ($valor === '...' || $valor === null || $valor === '') {
                            continue;
                        }

                        $leads->push(new SourceLead(
                            externalId: "{$source->key}:{$tabela}:{$variavel}:{$localidade}:{$periodo}",
                            title: "{$variableName} - {$localidade} - {$periodo}",
                            summary: "{$variableName}: {$valor} {$unidade} ({$localidade}, periodo {$periodo}).",
                            referenceUrl: "https://sidra.ibge.gov.br/tabela/{$tabela}",
                            referenceTitle: 'IBGE - Sistema IBGE de Recuperacao Automatica (SIDRA)',
                            facts: [
                                'indicador' => $variableName,
                                'unidade' => $unidade,
                                'localidade' => $localidade,
                                'periodo' => $periodo,
                                'valor' => $valor,
                            ],
                        ));
                    }
                }
            }
        }

        return $leads;
    }
}
