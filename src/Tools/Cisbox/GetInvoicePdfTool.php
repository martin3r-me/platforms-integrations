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
 * Das Rechnungs-PDF abrufen (GET /invoices/{barcodeNo}/pdf).
 */
class GetInvoicePdfTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Ab dieser Größe wird der Inhalt nur auf ausdrückliche Anforderung
     * ausgeliefert. Ein Beleg-PDF als Base64 im Tool-Ergebnis ist schnell
     * mehrere Megabyte groß — das sprengt jeden Kontext, ohne etwas zu sagen,
     * was nicht schon in den Rechnungsdaten steht.
     */
    public const INLINE_LIMIT_BYTES = 262144;

    public function getName(): string
    {
        return 'integrations.cisbox.invoice.pdf.GET';
    }

    public function getDescription(): string
    {
        return 'Lädt das Beleg-PDF einer Rechnung (GET /invoices/{barcodeNo}/pdf). Liefert standardmäßig '
            . 'nur Größe, MIME-Typ und Dateinamen; der Base64-Inhalt kommt erst mit "include_data": true '
            . 'und nur, wenn die Datei klein genug ist — ein mehrere Megabyte großes PDF gehört nicht in '
            . 'den Gesprächsverlauf. Die Rechnungsdaten selbst liefert integrations.cisbox.invoice.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'barcodeNo' => [
                    'type' => 'integer',
                    'description' => 'Barcode-Nummer der Rechnung.',
                ],
                'include_data' => [
                    'type' => 'boolean',
                    'description' => 'Den Base64-Inhalt mitliefern. Nur bis '
                        . (self::INLINE_LIMIT_BYTES / 1024) . ' KB, darüber bleibt er außen vor.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten cisbox-Verbindung.',
                ],
            ],
            'required' => ['barcodeNo'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['barcodeNo'])) {
            return $error;
        }

        $barcodeNo = (int) $arguments['barcodeNo'];

        try {
            $pdf = app(CisboxApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->getInvoicePdf($context->user, $barcodeNo);

            $result = [
                'barcodeNo' => $barcodeNo,
                'filename' => $pdf['filename'],
                'mime' => $pdf['mime'],
                'size' => $pdf['size'],
                'size_human' => $this->humanSize($pdf['size']),
            ];

            if ($arguments['include_data'] ?? false) {
                if ($pdf['size'] <= self::INLINE_LIMIT_BYTES) {
                    $result['data_base64'] = $pdf['base64'];
                } else {
                    $result['data_base64'] = null;
                    $result['hinweis'] = sprintf(
                        'Das PDF ist mit %s größer als die Grenze von %s und wurde deshalb nicht eingebettet.',
                        $this->humanSize($pdf['size']),
                        $this->humanSize(self::INLINE_LIMIT_BYTES)
                    );
                }
            }

            return ToolResult::success($result);
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'invoice', 'pdf', 'beleg', 'download'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
