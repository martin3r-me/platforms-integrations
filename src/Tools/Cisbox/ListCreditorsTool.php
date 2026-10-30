<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Tools\Cisbox\Concerns\GuardsArguments;

/**
 * Kreditoren auflisten, einzeln abrufen oder ihre Artikel laden.
 */
class ListCreditorsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.cisbox.creditors.GET';
    }

    public function getDescription(): string
    {
        return 'Kreditoren (Lieferanten) aus cisbox. Ohne Angabe die Liste (GET /creditors) mit Anschrift, '
            . 'Bankverbindung, Zahlungsbedingungen und Sperren; mit "creditorNo" der einzelne Kreditor '
            . 'inklusive Ansprechpartnern und Freigabe-Workflow; mit "creditorNo" und "items": true '
            . 'stattdessen seine Artikel mit Preishistorie (letzter, niedrigster, höchster, '
            . 'durchschnittlicher Einkaufspreis).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'creditorNo' => [
                    'type' => 'integer',
                    'description' => 'Kreditorennummer. Ohne Angabe wird die Liste geliefert.',
                ],
                'items' => [
                    'type' => 'boolean',
                    'description' => 'Nur zusammen mit "creditorNo": liefert die Artikel dieses Kreditors '
                        . '(GET /creditors/{creditorNo}/items) statt seiner Stammdaten.',
                ],
                'updatedSince' => [
                    'type' => 'string',
                    'description' => 'Nur seit diesem Zeitpunkt geänderte Kreditoren (ISO-8601). '
                        . 'Gilt nur für die Liste.',
                ],
            ], $this->paginationSchema()),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardSearch($arguments)) {
            return $error;
        }

        $creditorNo = $arguments['creditorNo'] ?? null;
        $wantsItems = (bool) ($arguments['items'] ?? false);

        if ($wantsItems && $creditorNo === null) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Artikel gibt es nur je Kreditor — bitte zusätzlich "creditorNo" angeben.'
            );
        }

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            if ($creditorNo === null) {
                $query = $this->listQuery($arguments, ['updatedSince']);

                return ToolResult::success(
                    $this->runList($service, $context->user, '/creditors', [], $query, $arguments)
                );
            }

            $pathParams = ['creditorNo' => (int) $creditorNo];

            if ($wantsItems) {
                // Die Artikelliste kennt kein updatedSince — nur Paginierung und Volltextsuche.
                $query = $this->listQuery($arguments);
                unset($query['updatedSince']);

                return ToolResult::success($this->runList(
                    $service,
                    $context->user,
                    '/creditors/{creditorNo}/items',
                    $pathParams,
                    $query,
                    $arguments
                ));
            }

            $response = $service->call($context->user, 'GET', '/creditors/{creditorNo}', $pathParams);
            $creditor = $service->single($response);

            if ($creditor === null) {
                return ToolResult::error('NOT_FOUND', "Kein Kreditor mit der Nummer {$creditorNo} gefunden.");
            }

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $projected = CisboxApiService::projectFields($creditor, $arguments['fields']);
                $creditor = is_array($projected) ? $projected : $creditor;
            }

            return ToolResult::success([
                'data' => $creditor,
                'request_id' => $response['request_id'] ?? null,
            ]);
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'kreditoren', 'lieferanten', 'artikel', 'stammdaten'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
