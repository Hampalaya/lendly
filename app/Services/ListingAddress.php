<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ListingAddress
{
    public function lookup(float $latitude, float $longitude): ?string
    {
        return $this->resolve($latitude, $longitude)['address'];
    }

    /** @return array{address: ?string, retry_after?: int} */
    public function resolve(float $latitude, float $longitude): array
    {
        $endpoint = config('services.listing_geocoder.url');
        if (! $endpoint) {
            return ['address' => null];
        }
        $key = 'listing-address:'.hash('sha256', $endpoint.':'.sprintf('%.5f,%.5f', $latitude, $longitude));
        if ($cached = Cache::get($key)) {
            return ['address' => $cached];
        }
        // Cache repeated pins and allow at most one provider request per second.
        if (! Cache::add('listing-address-provider-slot', true, 1)) {
            // A busy provider slot is temporary, not a failed address lookup.
            return ['address' => null, 'retry_after' => 1];
        }
        try {
            $response = Http::acceptJson()->withUserAgent('Lendly/1.0 (+'.config('app.url').')')
                ->connectTimeout(3)->timeout(7)->get($endpoint, [
                    'lat' => $latitude, 'lon' => $longitude, 'limit' => 1, 'lang' => 'en',
                ]);
            if (! $response->successful()) {
                return ['address' => null];
            }
            $properties = $response->json('features.0.properties');
            if (! is_array($properties)) {
                return ['address' => null];
            }
            $street = trim(($properties['housenumber'] ?? '').' '.($properties['street'] ?? ''));
            $parts = collect([$properties['name'] ?? null, $street, $properties['district'] ?? null,
                $properties['city'] ?? $properties['locality'] ?? $properties['county'] ?? null,
                $properties['state'] ?? null, $properties['postcode'] ?? null, $properties['country'] ?? null])
                ->filter(fn ($part) => is_string($part) && trim($part) !== '')
                ->map(fn ($part) => Str::squish(strip_tags($part)))->unique()->values();
            $address = Str::limit($parts->implode(', '), 255, '');
            if ($address === '') {
                return ['address' => null];
            }
            Cache::put($key, $address, now()->addDay());

            return ['address' => $address];
        } catch (Throwable) {
            return ['address' => null];
        }
    }
}
