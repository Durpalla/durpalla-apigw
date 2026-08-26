<?php

namespace App\Services\Hotel\Suppliers;

use App\Services\Hotel\Contracts\HotelSupplierInterface;
use App\Services\Hotel\DTO\HotelSearchRequestDTO;
use App\Models\Supplier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RateHawkSupplier implements HotelSupplierInterface
{
    protected Supplier $supplier;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secret;

    public function __construct(Supplier $supplier)
    {
        $this->supplier = $supplier;
        $config = $supplier->config ?? [];
        $this->baseUrl = $config['base_url'] ?? 'https://api.ratehawk.com';
        $this->apiKey = $config['api_key'] ?? '';
        $this->secret = $config['secret'] ?? '';
    }

    public function search(HotelSearchRequestDTO $request): array
    {
        try {
            $payload = [
                'checkin' => Carbon::parse($request->checkIn)->format('Y-m-d'),
                'checkout' => Carbon::parse($request->checkOut)->format('Y-m-d'),
                'residency' => 'BD',
                'language' => 'en',
                'guests' => [
                    [
                        'adults' => $request->adults,
                        'children' => $request->children ?: [],
                    ]
                ],
                'region_id' => $request->cityId, // Assuming cityId maps to RateHawk region_id
                'currency' => 'BDT',
            ];

            $response = Http::withHeaders($this->authHeaders())
                ->timeout(30)
                ->post($this->baseUrl . '/v1/search/hotels/', $payload);

            if (!$response->successful()) {
                Log::error('RateHawk search failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            return $this->normalizeSearchResponse($response->json(), $request);
        } catch (\Exception $e) {
            Log::error('RateHawk search exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getAvailability(array $criteria): array
    {
        // RateHawk availability is included in search response
        return [];
    }

    public function recheckRate(string $rateKey): array
    {
        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->post($this->baseUrl . '/v1/check_rate/', [
                    'rate_key' => $rateKey,
                ]);

            if (!$response->successful()) {
                return [
                    'valid' => false,
                    'message' => 'Rate recheck failed',
                ];
            }

            $data = $response->json();
            return [
                'valid' => true,
                'price' => $data['price']['net'] ?? null,
                'currency' => $data['price']['currency'] ?? 'USD',
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('RateHawk recheck exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'valid' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function book(array $payload): array
    {
        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(30)
                ->post($this->baseUrl . '/v1/book/', $payload);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'RateHawk booking failed',
                    'raw' => $response->json() ?? $response->body(),
                ];
            }

            $data = $response->json();
            return [
                'success' => true,
                'status' => $this->mapBookingStatus($data),
                'supplier_booking_reference' => $data['booking_id'] ?? null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('RateHawk booking exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function cancel(string $supplierBookingReference, array $options = []): array
    {
        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->post($this->baseUrl . '/v1/cancel/', [
                    'booking_id' => $supplierBookingReference,
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'RateHawk cancel failed',
                    'raw' => $response->json() ?? $response->body(),
                ];
            }

            $data = $response->json();
            return [
                'success' => true,
                'status' => $this->mapBookingStatus($data),
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('RateHawk cancel exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getBookingStatus(string $supplierBookingReference): array
    {
        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->get($this->baseUrl . '/v1/bookings/status', [
                    'booking_reference' => $supplierBookingReference,
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'RateHawk status check failed',
                ];
            }

            $data = $response->json();
            return [
                'success' => true,
                'status' => $this->mapBookingStatus($data),
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('RateHawk status check exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getSupplierCode(): string
    {
        return 'ratehawk';
    }

    protected function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->apiKey . ':' . $this->secret),
        ];
    }

    protected function normalizeSearchResponse(array $data, HotelSearchRequestDTO $request): array
    {
        $offers = [];
        $hotels = $data['hotels'] ?? [];

        foreach ($hotels as $hotel) {
            $rates = $hotel['rates'] ?? [];
            foreach ($rates as $rate) {
                $offers[] = [
                    'hotel_id' => $hotel['id'] ?? null,
                    'hotel_name' => $hotel['name'] ?? '',
                    'room_type' => $rate['room_name'] ?? '',
                    'rate_plan' => $rate['name'] ?? '',
                    'rate_key' => $rate['rate_key'] ?? '',
                    'price' => $rate['price']['net'] ?? 0,
                    'currency' => $rate['price']['currency'] ?? 'USD',
                    'cancellation_policy' => $rate['cancellation_policy']['free_cancellation_until'] ?? null,
                    'meal_plan' => $rate['meal'] ?? 'RO',
                    'refundable' => $rate['refundable'] ?? false,
                    'supplier' => 'ratehawk',
                    'book_token' => encrypt([
                        'supplier' => 'ratehawk',
                        'rate_key' => $rate['rate_key'] ?? '',
                    ]),
                    'raw' => $rate,
                ];
            }
        }

        return $offers;
    }

    protected function mapBookingStatus(array $data): string
    {
        $status = $data['status'] ?? 'pending';
        $statusMap = [
            'confirmed' => 'confirmed',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
        ];

        return $statusMap[strtolower($status)] ?? 'pending';
    }
}
