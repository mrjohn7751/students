<?php

namespace App\Services\Hemis;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HEMIS REST API (rest/v1/data/*) uchun yengil client.
 * Faqat o'qish (GET) operatsiyalari — UniRegistry HEMIS yozuvlarini o'zgartirmaydi.
 */
class HemisClient
{
    private function http(): PendingRequest
    {
        $token = config('hemis.token');
        if (! $token) {
            throw new RuntimeException('HEMIS_API_TOKEN .env faylida ko\'rsatilmagan.');
        }

        return Http::baseUrl(rtrim(config('hemis.url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(config('hemis.timeout'))
            ->retry(4, function (int $attempt, \Throwable $e) {
                // 429 bo'lsa uzoqroq kutamiz
                if ($e instanceof RequestException && $e->response->status() === 429) {
                    return 5000 * $attempt;
                }

                return 1000 * $attempt;
            }, throw: true);
    }

    /**
     * Bitta sahifani olish.
     *
     * @return array{items: array<int, array>, pagination: array}
     */
    public function page(string $endpoint, int $page = 1, array $params = []): array
    {
        $response = $this->http()->get('/data/'.$endpoint, array_merge([
            'page' => $page,
            'limit' => config('hemis.page_size'),
        ], $params));

        $json = $response->json();
        if (! ($json['success'] ?? false)) {
            throw new RuntimeException("HEMIS {$endpoint}: ".($json['error'] ?? 'noma\'lum xato'));
        }

        return [
            'items' => $json['data']['items'] ?? [],
            'pagination' => $json['data']['pagination'] ?? ['page' => $page, 'pageCount' => $page, 'totalCount' => 0],
        ];
    }

    /**
     * Barcha sahifalarni ketma-ket aylanib chiqadi.
     *
     * @return Generator<int, array{items: array, page: int, pageCount: int, totalCount: int}>
     */
    public function pages(string $endpoint, array $params = []): Generator
    {
        $page = 1;
        do {
            $result = $this->page($endpoint, $page, $params);
            $pageCount = (int) ($result['pagination']['pageCount'] ?? $page);

            yield [
                'items' => $result['items'],
                'page' => $page,
                'pageCount' => $pageCount,
                'totalCount' => (int) ($result['pagination']['totalCount'] ?? 0),
            ];

            if (empty($result['items'])) {
                break;
            }
            $page++;
            usleep(config('hemis.throttle_us'));
        } while ($page <= $pageCount);
    }
}
