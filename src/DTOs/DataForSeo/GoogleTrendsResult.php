<?php

namespace Platform\Integrations\DTOs\DataForSeo;

/**
 * DTO für DataForSEO Google Trends Explore Ergebnisse
 *
 * @see https://docs.dataforseo.com/v3/keywords_data/google_trends/explore/live/
 */
class GoogleTrendsResult
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?array $interestOverTime,
        public readonly ?int $averageInterest,
        public readonly ?int $peakInterest,
        public readonly ?string $peakDate,
    ) {}

    public static function fromApiResult(array $data, string $keyword): self
    {
        $dataPoints = $data['data'] ?? [];
        $values = [];

        foreach ($dataPoints as $point) {
            $pointValues = $point['values'] ?? [];
            $value = $pointValues[0] ?? null;
            $values[] = [
                'date_from' => $point['date_from'] ?? null,
                'date_to' => $point['date_to'] ?? null,
                'value' => $value,
            ];
        }

        $numericValues = array_filter(
            array_column($values, 'value'),
            fn($v) => $v !== null
        );

        $average = count($numericValues) > 0
            ? (int) round(array_sum($numericValues) / count($numericValues))
            : null;

        $peak = count($numericValues) > 0 ? (int) max($numericValues) : null;

        $peakDate = null;
        if ($peak !== null) {
            foreach ($values as $v) {
                if ($v['value'] === $peak) {
                    $peakDate = $v['date_from'];
                    break;
                }
            }
        }

        return new self(
            keyword: $keyword,
            interestOverTime: $values ?: null,
            averageInterest: $average,
            peakInterest: $peak,
            peakDate: $peakDate,
        );
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'interest_over_time' => $this->interestOverTime,
            'average_interest' => $this->averageInterest,
            'peak_interest' => $this->peakInterest,
            'peak_date' => $this->peakDate,
        ];
    }
}
