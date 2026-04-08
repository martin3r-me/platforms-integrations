<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsHubspotCompany;
use Platform\Integrations\Models\IntegrationsHubspotContact;
use Platform\Integrations\Models\IntegrationsHubspotDeal;
use Platform\Integrations\Models\IntegrationsHubspotEngagement;

/**
 * Service für die Synchronisierung von HubSpot CRM Daten
 * (Contacts, Companies, Deals und Engagements)
 */
class HubspotCrmSyncService
{
    protected const BASE_URL = 'https://api.hubapi.com';
    protected const PAGE_SIZE = 100;

    /** Standard-Properties für Contacts */
    protected const CONTACT_PROPERTIES = [
        'email', 'firstname', 'lastname', 'phone', 'company',
        'lifecyclestage', 'hs_lead_status', 'hubspot_owner_id',
        'createdate', 'lastmodifieddate', 'jobtitle', 'website',
        'city', 'country', 'address',
    ];

    /** Standard-Properties für Companies */
    protected const COMPANY_PROPERTIES = [
        'name', 'domain', 'industry', 'phone', 'city', 'country',
        'hubspot_owner_id', 'createdate', 'hs_lastmodifieddate',
        'website', 'description', 'numberofemployees', 'annualrevenue',
    ];

    /** Standard-Properties für Deals */
    protected const DEAL_PROPERTIES = [
        'dealname', 'amount', 'pipeline', 'dealstage', 'closedate',
        'hubspot_owner_id', 'createdate', 'hs_lastmodifieddate',
        'dealtype', 'description', 'hs_deal_stage_probability',
    ];

    /** Standard-Properties für Engagements */
    protected const NOTE_PROPERTIES = ['hs_note_body', 'hs_timestamp', 'hubspot_owner_id', 'hs_createdate', 'hs_lastmodifieddate'];
    protected const CALL_PROPERTIES = ['hs_call_body', 'hs_call_title', 'hs_call_duration', 'hs_timestamp', 'hubspot_owner_id', 'hs_createdate', 'hs_lastmodifieddate'];
    protected const EMAIL_PROPERTIES = ['hs_email_subject', 'hs_email_text', 'hs_email_html', 'hs_timestamp', 'hubspot_owner_id', 'hs_createdate', 'hs_lastmodifieddate'];
    protected const MEETING_PROPERTIES = ['hs_meeting_title', 'hs_meeting_body', 'hs_meeting_start_time', 'hs_meeting_end_time', 'hs_timestamp', 'hubspot_owner_id', 'hs_createdate', 'hs_lastmodifieddate'];
    protected const TASK_PROPERTIES = ['hs_task_subject', 'hs_task_body', 'hs_task_status', 'hs_timestamp', 'hubspot_owner_id', 'hs_createdate', 'hs_lastmodifieddate'];

    protected HubspotIntegrationService $hubspotService;

    public function __construct(HubspotIntegrationService $hubspotService)
    {
        $this->hubspotService = $hubspotService;
    }

    /**
     * Orchestriert alle Sub-Syncs und gibt Counts zurück.
     *
     * @return array{contacts:int|string, companies:int|string, deals:int|string, engagements:int|string}
     */
    public function syncAllForConnection(IntegrationConnection $connection): array
    {
        $results = [];

        try {
            $results['contacts'] = $this->syncContacts($connection);
        } catch (\Exception $e) {
            $this->markConnectionError($connection, 'Contacts sync: ' . $e->getMessage());
            $results['contacts'] = 'error';
        }

        try {
            $results['companies'] = $this->syncCompanies($connection);
        } catch (\Exception $e) {
            $this->markConnectionError($connection, 'Companies sync: ' . $e->getMessage());
            $results['companies'] = 'error';
        }

        try {
            $results['deals'] = $this->syncDeals($connection);
        } catch (\Exception $e) {
            $this->markConnectionError($connection, 'Deals sync: ' . $e->getMessage());
            $results['deals'] = 'error';
        }

        try {
            $results['engagements'] = $this->syncEngagements($connection);
        } catch (\Exception $e) {
            $this->markConnectionError($connection, 'Engagements sync: ' . $e->getMessage());
            $results['engagements'] = 'error';
        }

        Log::info('HubSpot CRM sync completed', [
            'connection_id' => $connection->id,
            'user_id' => $connection->owner_user_id,
            'results' => $results,
        ]);

        return $results;
    }

    public function syncContacts(IntegrationConnection $connection): int
    {
        $token = $this->getToken($connection);
        $userId = $connection->owner_user_id;
        $count = 0;

        $query = [
            'limit' => self::PAGE_SIZE,
            'properties' => implode(',', self::CONTACT_PROPERTIES),
            'associations' => 'companies,deals',
        ];

        foreach ($this->paginatedFetch(self::BASE_URL . '/crm/v3/objects/contacts', $token, $query) as $page) {
            foreach (($page['results'] ?? []) as $item) {
                $props = $item['properties'] ?? [];

                IntegrationsHubspotContact::updateOrCreate(
                    [
                        'external_id' => (string) ($item['id'] ?? ''),
                        'user_id' => $userId,
                    ],
                    [
                        'email' => $props['email'] ?? null,
                        'first_name' => $props['firstname'] ?? null,
                        'last_name' => $props['lastname'] ?? null,
                        'phone' => $props['phone'] ?? null,
                        'company' => $props['company'] ?? null,
                        'lifecycle_stage' => $props['lifecyclestage'] ?? null,
                        'lead_status' => $props['hs_lead_status'] ?? null,
                        'owner_id' => $props['hubspot_owner_id'] ?? null,
                        'hubspot_created_at' => $this->parseDate($props['createdate'] ?? ($item['createdAt'] ?? null)),
                        'hubspot_updated_at' => $this->parseDate($props['lastmodifieddate'] ?? ($item['updatedAt'] ?? null)),
                        'metadata' => [
                            'properties' => $props,
                            'associations' => $item['associations'] ?? null,
                        ],
                        'integration_connection_id' => $connection->id,
                    ]
                );
                $count++;
            }
        }

        Log::info('HubSpot contacts synced', [
            'connection_id' => $connection->id,
            'user_id' => $userId,
            'count' => $count,
        ]);

        return $count;
    }

    public function syncCompanies(IntegrationConnection $connection): int
    {
        $token = $this->getToken($connection);
        $userId = $connection->owner_user_id;
        $count = 0;

        $query = [
            'limit' => self::PAGE_SIZE,
            'properties' => implode(',', self::COMPANY_PROPERTIES),
        ];

        foreach ($this->paginatedFetch(self::BASE_URL . '/crm/v3/objects/companies', $token, $query) as $page) {
            foreach (($page['results'] ?? []) as $item) {
                $props = $item['properties'] ?? [];

                IntegrationsHubspotCompany::updateOrCreate(
                    [
                        'external_id' => (string) ($item['id'] ?? ''),
                        'user_id' => $userId,
                    ],
                    [
                        'name' => $props['name'] ?? null,
                        'domain' => $props['domain'] ?? null,
                        'industry' => $props['industry'] ?? null,
                        'phone' => $props['phone'] ?? null,
                        'city' => $props['city'] ?? null,
                        'country' => $props['country'] ?? null,
                        'owner_id' => $props['hubspot_owner_id'] ?? null,
                        'hubspot_created_at' => $this->parseDate($props['createdate'] ?? ($item['createdAt'] ?? null)),
                        'hubspot_updated_at' => $this->parseDate($props['hs_lastmodifieddate'] ?? ($item['updatedAt'] ?? null)),
                        'metadata' => [
                            'properties' => $props,
                        ],
                        'integration_connection_id' => $connection->id,
                    ]
                );
                $count++;
            }
        }

        Log::info('HubSpot companies synced', [
            'connection_id' => $connection->id,
            'user_id' => $userId,
            'count' => $count,
        ]);

        return $count;
    }

    public function syncDeals(IntegrationConnection $connection): int
    {
        $token = $this->getToken($connection);
        $userId = $connection->owner_user_id;
        $count = 0;

        $query = [
            'limit' => self::PAGE_SIZE,
            'properties' => implode(',', self::DEAL_PROPERTIES),
            'associations' => 'contacts,companies',
        ];

        foreach ($this->paginatedFetch(self::BASE_URL . '/crm/v3/objects/deals', $token, $query) as $page) {
            foreach (($page['results'] ?? []) as $item) {
                $props = $item['properties'] ?? [];
                $associations = $this->extractAssociations($item['associations'] ?? []);

                IntegrationsHubspotDeal::updateOrCreate(
                    [
                        'external_id' => (string) ($item['id'] ?? ''),
                        'user_id' => $userId,
                    ],
                    [
                        'dealname' => $props['dealname'] ?? null,
                        'amount' => isset($props['amount']) && $props['amount'] !== '' ? (float) $props['amount'] : null,
                        'pipeline' => $props['pipeline'] ?? null,
                        'dealstage' => $props['dealstage'] ?? null,
                        'close_date' => $this->parseDate($props['closedate'] ?? null),
                        'owner_id' => $props['hubspot_owner_id'] ?? null,
                        'hubspot_created_at' => $this->parseDate($props['createdate'] ?? ($item['createdAt'] ?? null)),
                        'hubspot_updated_at' => $this->parseDate($props['hs_lastmodifieddate'] ?? ($item['updatedAt'] ?? null)),
                        'metadata' => [
                            'properties' => $props,
                        ],
                        'associations' => $associations,
                        'integration_connection_id' => $connection->id,
                    ]
                );
                $count++;
            }
        }

        Log::info('HubSpot deals synced', [
            'connection_id' => $connection->id,
            'user_id' => $userId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Iteriert über alle 5 Engagement-Subtypen.
     */
    public function syncEngagements(IntegrationConnection $connection): int
    {
        $total = 0;

        $subtypes = [
            'note' => ['path' => '/crm/v3/objects/notes', 'props' => self::NOTE_PROPERTIES],
            'call' => ['path' => '/crm/v3/objects/calls', 'props' => self::CALL_PROPERTIES],
            'email' => ['path' => '/crm/v3/objects/emails', 'props' => self::EMAIL_PROPERTIES],
            'meeting' => ['path' => '/crm/v3/objects/meetings', 'props' => self::MEETING_PROPERTIES],
            'task' => ['path' => '/crm/v3/objects/tasks', 'props' => self::TASK_PROPERTIES],
        ];

        foreach ($subtypes as $type => $config) {
            try {
                $total += $this->syncEngagementSubtype($connection, $type, $config['path'], $config['props']);
            } catch (\Exception $e) {
                Log::warning('HubSpot engagement subtype sync failed', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->owner_user_id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $total;
    }

    protected function syncEngagementSubtype(
        IntegrationConnection $connection,
        string $type,
        string $path,
        array $properties
    ): int {
        $token = $this->getToken($connection);
        $userId = $connection->owner_user_id;
        $count = 0;

        $query = [
            'limit' => self::PAGE_SIZE,
            'properties' => implode(',', $properties),
            'associations' => 'contacts,companies,deals',
        ];

        foreach ($this->paginatedFetch(self::BASE_URL . $path, $token, $query) as $page) {
            foreach (($page['results'] ?? []) as $item) {
                $props = $item['properties'] ?? [];
                $associations = $this->extractAssociations($item['associations'] ?? []);

                [$subject, $body, $engagedAt] = $this->extractEngagementFields($type, $props);

                IntegrationsHubspotEngagement::updateOrCreate(
                    [
                        'external_id' => (string) ($item['id'] ?? ''),
                        'user_id' => $userId,
                    ],
                    [
                        'engagement_type' => $type,
                        'subject' => $subject,
                        'body' => $body,
                        'engaged_at' => $engagedAt,
                        'owner_id' => $props['hubspot_owner_id'] ?? null,
                        'hubspot_created_at' => $this->parseDate($props['hs_createdate'] ?? ($item['createdAt'] ?? null)),
                        'hubspot_updated_at' => $this->parseDate($props['hs_lastmodifieddate'] ?? ($item['updatedAt'] ?? null)),
                        'metadata' => [
                            'properties' => $props,
                        ],
                        'associations' => $associations,
                        'integration_connection_id' => $connection->id,
                    ]
                );
                $count++;
            }
        }

        Log::info('HubSpot engagement subtype synced', [
            'connection_id' => $connection->id,
            'user_id' => $userId,
            'type' => $type,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?\Carbon\Carbon}
     */
    protected function extractEngagementFields(string $type, array $props): array
    {
        $timestamp = $this->parseDate($props['hs_timestamp'] ?? null);

        switch ($type) {
            case 'note':
                return [null, $props['hs_note_body'] ?? null, $timestamp];
            case 'call':
                return [$props['hs_call_title'] ?? null, $props['hs_call_body'] ?? null, $timestamp];
            case 'email':
                return [
                    $props['hs_email_subject'] ?? null,
                    $props['hs_email_text'] ?? ($props['hs_email_html'] ?? null),
                    $timestamp,
                ];
            case 'meeting':
                return [
                    $props['hs_meeting_title'] ?? null,
                    $props['hs_meeting_body'] ?? null,
                    $this->parseDate($props['hs_meeting_start_time'] ?? ($props['hs_timestamp'] ?? null)),
                ];
            case 'task':
                return [$props['hs_task_subject'] ?? null, $props['hs_task_body'] ?? null, $timestamp];
            default:
                return [null, null, $timestamp];
        }
    }

    /**
     * Paginierter Fetch über HubSpot v3 Cursor-Pagination.
     *
     * @return \Generator<int, array>
     */
    protected function paginatedFetch(string $url, string $token, array $query): \Generator
    {
        $after = null;

        do {
            $currentQuery = $query;
            if ($after !== null) {
                $currentQuery['after'] = $after;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get($url, $currentQuery);

            if (!$response->successful()) {
                $error = $response->json()['message'] ?? ('HTTP ' . $response->status());
                throw new \RuntimeException('HubSpot API Fehler: ' . $error);
            }

            $data = $response->json();
            yield $data;

            $after = $data['paging']['next']['after'] ?? null;
        } while ($after !== null);
    }

    /**
     * Extrahiert verknüpfte External IDs aus dem HubSpot Associations-Payload.
     */
    protected function extractAssociations(array $associations): array
    {
        $result = [];
        foreach ($associations as $type => $payload) {
            $ids = [];
            foreach (($payload['results'] ?? []) as $assoc) {
                if (isset($assoc['id'])) {
                    $ids[] = (string) $assoc['id'];
                }
            }
            if (!empty($ids)) {
                $result[$type] = array_values(array_unique($ids));
            }
        }
        return $result;
    }

    protected function parseDate(mixed $value): ?\Carbon\Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            // HubSpot liefert teils Unix-Millis als String, teils ISO-8601
            if (is_numeric($value)) {
                $value = (int) $value;
                // Millis erkennen (13 Stellen)
                if ($value > 10_000_000_000) {
                    return \Carbon\Carbon::createFromTimestampMs($value);
                }
                return \Carbon\Carbon::createFromTimestamp($value);
            }
            return \Carbon\Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getToken(IntegrationConnection $connection): string
    {
        $token = $this->hubspotService->getApiToken($connection);
        if (!$token) {
            throw new \RuntimeException('Kein HubSpot API-Token vorhanden.');
        }
        return $token;
    }

    protected function markConnectionError(IntegrationConnection $connection, string $error): void
    {
        $connection->status = 'error';
        $connection->last_error = $error;
        $connection->save();

        Log::warning('HubSpot sync error marked on connection', [
            'connection_id' => $connection->id,
            'user_id' => $connection->owner_user_id,
            'error' => $error,
        ]);
    }
}
