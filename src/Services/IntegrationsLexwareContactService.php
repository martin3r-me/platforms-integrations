<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsLexwareContact;

/**
 * Service für die Synchronisierung von Lexware Kontakten
 */
class IntegrationsLexwareContactService
{
    protected LexwareIntegrationService $lexwareService;

    public function __construct(LexwareIntegrationService $lexwareService)
    {
        $this->lexwareService = $lexwareService;
    }

    /**
     * Synchronisiert Kontakte von Lexware für einen User
     *
     * @return IntegrationsLexwareContact[]
     */
    public function syncContactsForUser(IntegrationConnection $connection): array
    {
        $apiToken = $this->lexwareService->getApiToken($connection);

        if (!$apiToken) {
            throw new \RuntimeException('Kein Lexware API-Token vorhanden.');
        }

        $userId = $connection->owner_user_id;
        $synced = [];
        $page = 0;
        $pageSize = 100;

        try {
            do {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Accept' => 'application/json',
                ])->get('https://api.lexoffice.io/v1/contacts', [
                    'page' => $page,
                    'size' => $pageSize,
                ]);

                if (!$response->successful()) {
                    $error = $response->json()['message'] ?? 'Unbekannter API-Fehler';
                    throw new \RuntimeException('Lexware API Fehler: ' . $error);
                }

                $data = $response->json();
                $contacts = $data['content'] ?? [];
                $totalPages = $data['totalPages'] ?? 1;

                foreach ($contacts as $contactData) {
                    $contact = $this->upsertContact($contactData, $connection, $userId);
                    $synced[] = $contact;
                }

                $page++;
            } while ($page < $totalPages);

            Log::info('Lexware contacts synced', [
                'user_id' => $userId,
                'connection_id' => $connection->id,
                'count' => count($synced),
            ]);

            return $synced;
        } catch (\Exception $e) {
            Log::error('Lexware contacts sync failed', [
                'user_id' => $userId,
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Erstellt oder aktualisiert einen Lexware-Kontakt
     */
    protected function upsertContact(array $data, IntegrationConnection $connection, int $userId): IntegrationsLexwareContact
    {
        $externalId = $data['id'] ?? '';

        // Kontakttyp bestimmen
        // LexOffice API returns roles as associative array: {"customer": {...}, "vendor": {...}}
        // The role names are the array keys, not nested 'name' fields.
        $roles = $data['roles'] ?? [];
        $roleNames = array_map('strtolower', array_map('strval', array_keys($roles)));
        $isCustomer = in_array('customer', $roleNames);
        $isVendor = in_array('vendor', $roleNames);

        $contactType = 'customer';
        if ($isCustomer && $isVendor) {
            $contactType = 'both';
        } elseif ($isVendor) {
            $contactType = 'vendor';
        }

        // Personendaten extrahieren
        $person = $data['person'] ?? [];
        $company = $data['company'] ?? [];
        $addresses = $data['addresses'] ?? [];
        $emailAddresses = $data['emailAddresses'] ?? [];
        $phoneNumbers = $data['phoneNumbers'] ?? [];

        // Primäre E-Mail und Telefon finden
        $primaryEmail = null;
        $primaryPhone = null;

        if (!empty($emailAddresses['business'])) {
            $primaryEmail = $emailAddresses['business'][0] ?? null;
        } elseif (!empty($emailAddresses['private'])) {
            $primaryEmail = $emailAddresses['private'][0] ?? null;
        } elseif (!empty($emailAddresses['other'])) {
            $primaryEmail = $emailAddresses['other'][0] ?? null;
        }

        if (!empty($phoneNumbers['business'])) {
            $primaryPhone = $phoneNumbers['business'][0] ?? null;
        } elseif (!empty($phoneNumbers['mobile'])) {
            $primaryPhone = $phoneNumbers['mobile'][0] ?? null;
        } elseif (!empty($phoneNumbers['private'])) {
            $primaryPhone = $phoneNumbers['private'][0] ?? null;
        }

        return IntegrationsLexwareContact::updateOrCreate(
            [
                'external_id' => $externalId,
                'user_id' => $userId,
            ],
            [
                'contact_number' => $data['contactNumber'] ?? null,
                'contact_type' => $contactType,
                'company_name' => $company['name'] ?? null,
                'first_name' => $person['firstName'] ?? null,
                'last_name' => $person['lastName'] ?? null,
                'email' => $primaryEmail,
                'phone' => $primaryPhone,
                'note' => $data['note'] ?? null,
                'is_archived' => $data['archived'] ?? false,
                'lexware_created_at' => isset($data['createdDate']) ? \Carbon\Carbon::parse($data['createdDate']) : null,
                'lexware_updated_at' => isset($data['updatedDate']) ? \Carbon\Carbon::parse($data['updatedDate']) : null,
                'metadata' => [
                    'version' => $data['version'] ?? null,
                    'addresses' => $addresses,
                    'emailAddresses' => $emailAddresses,
                    'phoneNumbers' => $phoneNumbers,
                    'person' => $person,
                    'company' => $company,
                    'roles' => $roles,
                ],
                'integration_connection_id' => $connection->id,
            ]
        );
    }
}
