<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PsgcController extends Controller
{
    public function provinces()
    {
        return $this->lookup('provinces');
    }

    public function cities(string $provinceCode)
    {
        return $this->lookup(
            "provinces/{$provinceCode}/cities-municipalities"
        );
    }

    public function regionCities(string $regionCode)
    {
        abort_unless($regionCode === '1300000000', 404);
        return $this->lookup("regions/{$regionCode}/cities-municipalities");
    }

    public function barangays(string $cityCode)
    {
        return $this->lookup(
            "cities-municipalities/{$cityCode}/barangays"
        );
    }

    private function lookup(string $path)
    {
        try {
            $items = Cache::remember(
                'psgc.cloud.lookup.v2.' . sha1($path),
                now()->addDay(),
                function () use ($path) {
                    $response = Http::acceptJson()
                        ->connectTimeout(5)
                        ->timeout(20)
                        ->get("https://psgc.cloud/api/{$path}");

                    $response->throw();

                    $data = $response->json();

                    if (! is_array($data) || ! array_is_list($data)) {
                        throw new RuntimeException(
                            'Unexpected address response.'
                        );
                    }

                    foreach ($data as $item) {
                        if (! is_array($item)
                            || ! isset($item['code'], $item['name'])
                            || ! is_string($item['code'])
                            || ! preg_match('/^\d{10}$/', $item['code'])
                            || ! is_string($item['name'])
                            || trim($item['name']) === '') {
                            throw new RuntimeException(
                                'Invalid address data.'
                            );
                        }
                    }

                    return collect($data)
                        ->map(fn ($item) => [
                            'code' => $item['code'],
                            'name' => trim($item['name']),
                        ])
                        ->sortBy('name')
                        ->values()
                        ->all();
                }
            );

            return response()->json(['data' => $items]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Address service is unavailable. Please retry.',
            ], 503);
        }
    }
}