<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO Google Business Info Ergebnisse
 *
 * @see https://docs.dataforseo.com/v3/business_data/google/my_business_info/live/
 */
class GoogleBusinessInfoResult
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $cid,
        public readonly ?string $placeId,
        public readonly ?float $ratingValue,
        public readonly ?int $ratingVotesCount,
        public readonly ?array $ratingDistribution,
        public readonly ?bool $isClaimed,
        public readonly ?string $category,
        public readonly ?array $additionalCategories,
        public readonly ?string $address,
        public readonly ?array $addressInfo,
        public readonly ?string $phone,
        public readonly ?string $url,
        public readonly ?string $domain,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $currentStatus,
        public readonly ?array $workTime,
        public readonly ?int $totalPhotos,
        public readonly ?array $placeTopics,
        public readonly ?array $localBusinessLinks,
    ) {}

    public static function fromApiResult(array $data): self
    {
        // Rating distribution: {1: n, 2: n, 3: n, 4: n, 5: n}
        $ratingDistribution = null;
        if (isset($data['rating']['rating_distribution'])) {
            $ratingDistribution = $data['rating']['rating_distribution'];
        }

        // Place topics: mixed format from API — normalize to [{keyword, count}, ...]
        $placeTopics = null;
        if (isset($data['place_topics']) && is_array($data['place_topics'])) {
            $placeTopics = [];
            foreach ($data['place_topics'] as $key => $value) {
                if (is_array($value)) {
                    $placeTopics[] = [
                        'keyword' => $value['keyword'] ?? $value['topic'] ?? null,
                        'count' => $value['count'] ?? $value['reviews_count'] ?? null,
                    ];
                } elseif (is_string($key)) {
                    $placeTopics[] = ['keyword' => $key, 'count' => $value];
                }
            }
        }

        // Local business links: mixed format — normalize to [{type, url}, ...]
        $localBusinessLinks = null;
        if (isset($data['local_business_links']) && is_array($data['local_business_links'])) {
            $localBusinessLinks = [];
            foreach ($data['local_business_links'] as $key => $value) {
                if (is_array($value)) {
                    $localBusinessLinks[] = [
                        'type' => $value['type'] ?? $key ?? null,
                        'url' => $value['url'] ?? $value['link'] ?? null,
                    ];
                } elseif (is_string($value)) {
                    $localBusinessLinks[] = ['type' => $key, 'url' => $value];
                }
            }
        }

        return new self(
            title: (string) ($data['title'] ?? ''),
            cid: isset($data['cid']) ? (string) $data['cid'] : null,
            placeId: isset($data['place_id']) ? (string) $data['place_id'] : null,
            ratingValue: isset($data['rating']['value']) ? (float) $data['rating']['value'] : null,
            ratingVotesCount: isset($data['rating']['votes_count']) ? (int) $data['rating']['votes_count'] : null,
            ratingDistribution: $ratingDistribution,
            isClaimed: isset($data['is_claimed']) ? (bool) $data['is_claimed'] : null,
            category: isset($data['category']) ? (string) $data['category'] : null,
            additionalCategories: $data['additional_categories'] ?? null,
            address: isset($data['address']) ? (string) $data['address'] : null,
            addressInfo: $data['address_info'] ?? null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            url: isset($data['url']) ? (string) $data['url'] : null,
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            currentStatus: isset($data['current_status']) ? (string) $data['current_status'] : null,
            workTime: $data['work_time'] ?? null,
            totalPhotos: isset($data['total_photos']) ? (int) $data['total_photos'] : null,
            placeTopics: $placeTopics,
            localBusinessLinks: $localBusinessLinks,
        );
    }

    /**
     * @param array $results Array von API-Ergebnissen
     * @return self[]
     */
    public static function fromApiResults(array $results): array
    {
        return array_map(fn(array $item) => self::fromApiResult($item), $results);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'cid' => $this->cid,
            'place_id' => $this->placeId,
            'rating_value' => $this->ratingValue,
            'rating_votes_count' => $this->ratingVotesCount,
            'rating_distribution' => $this->ratingDistribution,
            'is_claimed' => $this->isClaimed,
            'category' => $this->category,
            'additional_categories' => $this->additionalCategories,
            'address' => $this->address,
            'address_info' => $this->addressInfo,
            'phone' => $this->phone,
            'url' => $this->url,
            'domain' => $this->domain,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'current_status' => $this->currentStatus,
            'work_time' => $this->workTime,
            'total_photos' => $this->totalPhotos,
            'place_topics' => $this->placeTopics,
            'local_business_links' => $this->localBusinessLinks,
        ];
    }
}
