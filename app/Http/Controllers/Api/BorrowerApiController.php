<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Borrower;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class BorrowerApiController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($data['search'] ?? '');

        $borrowers = Borrower::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'phone',
                'email',
                'address',
                'status',
            ])
            ->when(!empty($data['status']), fn ($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', [$data['status']]))
            ->withCount('loans')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json($borrowers);
    }

    public function show(Borrower $borrower)
    {
        $borrower->loadCount('loans');

        return response()->json([
            'id' => $borrower->id,
            'first_name' => $borrower->first_name,
            'last_name' => $borrower->last_name,
            'phone' => $borrower->phone,
            'email' => $borrower->email,
            'address' => $borrower->address,
            'status' => $borrower->status,
            'notes' => $borrower->notes,
            'region_code' => $borrower->region_code,
            'region_name' => $borrower->region_name,
            'province_code' => $borrower->province_code,
            'province_name' => $borrower->province_name,
            'city_municipality_code' => $borrower->city_municipality_code,
            'city_municipality_name' => $borrower->city_municipality_name,
            'barangay_code' => $borrower->barangay_code,
            'barangay_name' => $borrower->barangay_name,
            'street_house' => $borrower->street_house,
            'loans_count' => $borrower->loans_count,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeWrite($request);

        $data = $this->validatedData($request, true);
        $data['status'] = 'active';

        $borrower = DB::transaction(function () use ($request, $data) {
            $borrower = Borrower::create($data);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'subject_type' => Borrower::class,
                'subject_id' => $borrower->id,
                'action' => 'created',
                'description' => 'Created borrower '
                    . $borrower->first_name . ' '
                    . $borrower->last_name,
                'changes' => $data,
            ]);

            return $borrower;
        });

        return response()->json([
            'message' => 'Borrower created successfully.',
            'data' => $borrower,
        ], 201);
    }

    public function update(Request $request, Borrower $borrower)
    {
        $this->authorizeWrite($request);

        // Address is updated only when address fields are submitted.
        $addressFields = [
            'region_code',
            'province_code',
            'city_municipality_code',
            'barangay_code',
            'street_house',
        ];

        $withAddress = count(array_intersect(
            $addressFields,
            array_keys($request->all())
        )) > 0;

        $data = $this->validatedData($request, $withAddress);

        $updated = DB::transaction(function () use (
            $request,
            $borrower,
            $data
        ) {
            $record = Borrower::query()
                ->whereKey($borrower->id)
                ->lockForUpdate()
                ->firstOrFail();

            $record->fill($data);
            $dirty = $record->getDirty();

            if ($dirty !== []) {
                $before = [];

                foreach (array_keys($dirty) as $field) {
                    $before[$field] = $record->getOriginal($field);
                }

                $record->save();

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'subject_type' => Borrower::class,
                    'subject_id' => $record->id,
                    'action' => 'updated',
                    'description' => 'Updated borrower '
                        . $record->first_name . ' '
                        . $record->last_name,
                    'changes' => [
                        'before' => $before,
                        'after' => $dirty,
                    ],
                ]);
            }

            return $record;
        });

        return response()->json([
            'message' => 'Borrower updated successfully.',
            'data' => $updated,
        ]);
    }

    private function authorizeWrite(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin()
                || $request->user()?->isStaff(),
            403
        );
    }

    private function validatedData(
        Request $request,
        bool $withAddress
    ): array {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        $isNcr = $request->input('region_code') === '1300000000';
        if ($withAddress) {
            $rules['region_code'] = ['nullable', 'in:1300000000'];
            foreach ([
                'province_code',
                'city_municipality_code',
                'barangay_code',
            ] as $field) {
                $rules[$field] = [
                    'required',
                    'string',
                    'regex:/^[0-9]{10}$/',
                ];
            }

            $rules['province_code'] = $isNcr
                ? ['nullable', 'prohibited']
                : ['required', 'string', 'regex:/^[0-9]{10}$/'];

            $rules['street_house'] = [
                'required',
                'string',
                'max:150',
            ];
        }

        $data = $request->validate($rules);

        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }

        $requiredFields = ['first_name', 'last_name'];

        if ($withAddress) {
            $requiredFields[] = 'street_house';
        }

        foreach ($requiredFields as $field) {
            if ($data[$field] === '') {
                throw ValidationException::withMessages([
                    $field => ['This field cannot be blank.'],
                ]);
            }
        }

        if (! $withAddress) {
            return $data;
        }

        $province = null;
        if ($isNcr) {
            $cityPath = 'regions/1300000000/cities-municipalities';
            $data['region_code'] = '1300000000';
            $data['region_name'] = 'National Capital Region';
            $data['province_code'] = null;
        } else {
            $province = collect($this->addressItems('provinces'))
                ->firstWhere('code', $data['province_code']);
            if (!$province) {
                throw ValidationException::withMessages(['province_code' => ['Select a valid province.']]);
            }
            $cityPath = "provinces/{$data['province_code']}/cities-municipalities";
            $data['region_code'] = null;
            $data['region_name'] = null;
        }
        $city = collect($this->addressItems($cityPath))
            ->firstWhere('code', $data['city_municipality_code']);

        if (! $city) {
            throw ValidationException::withMessages([
                'city_municipality_code' => [
                    'Select a city or municipality belonging to this province or NCR.',
                ],
            ]);
        }

        $barangay = collect($this->addressItems(
            "cities-municipalities/{$data['city_municipality_code']}/barangays"
        ))->firstWhere('code', $data['barangay_code']);

        if (! $barangay) {
            throw ValidationException::withMessages([
                'barangay_code' => [
                    'Select a barangay belonging to this city or municipality.',
                ],
            ]);
        }

        $data['province_name'] = $province['name'] ?? null;
        $data['city_municipality_name'] = $city['name'];
        $data['barangay_name'] = $barangay['name'];

        $data['address'] = implode(', ', [
            $data['street_house'],
            $data['barangay_name'],
            $data['city_municipality_name'],
            $data['province_name'] ?? $data['region_name'],
        ]);

        if (mb_strlen($data['address']) > 255) {
            throw ValidationException::withMessages([
                'street_house' => [
                    'The full address is too long. Shorten the street/house details.',
                ],
            ]);
        }

        return $data;
    }

    private function addressItems(string $path): array
    {
        try {
            return Cache::remember(
                'psgc.cloud.lookup.v2.' . sha1($path),
                now()->addDay(),
                function () use ($path) {
                    $response = Http::acceptJson()
                        ->connectTimeout(5)
                        ->timeout(20)
                        ->get("https://psgc.cloud/api/{$path}");

                    $response->throw();
                    $items = $response->json();

                    if (! is_array($items) || ! array_is_list($items)) {
                        throw new \RuntimeException(
                            'Invalid address response.'
                        );
                    }

                    foreach ($items as $item) {
                        if (
                            ! is_array($item)
                            || ! isset($item['code'], $item['name'])
                            || ! is_string($item['code'])
                            || ! preg_match(
                                '/^[0-9]{10}$/',
                                $item['code']
                            )
                            || ! is_string($item['name'])
                            || trim($item['name']) === ''
                        ) {
                            throw new \RuntimeException(
                                'Invalid address data.'
                            );
                        }
                    }

                    return collect($items)
                        ->map(fn ($item) => [
                            'code' => $item['code'],
                            'name' => trim($item['name']),
                        ])
                        ->sortBy('name')
                        ->values()
                        ->all();
                }
            );
        } catch (\Throwable $exception) {
            report($exception);

            throw new HttpResponseException(
                response()->json([
                    'message' => 'Address service is unavailable. Please retry.',
                ], 503)
            );
        }
    }
}