<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Customer\Models\CustomerAddress;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerAddressController extends Controller
{
    private const MAX_ADDRESSES = 10;

    public function index(Request $request): Response
    {
        $user = $this->customer($request);

        return Inertia::render('Storefront/Account/Addresses', [
            'addresses' => $user->addresses()
                ->with('district:id,name_en,zone_type')
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->map(fn (CustomerAddress $address): array => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'district_id' => $address->district_id,
                    'district_name' => $address->district?->name_en,
                    'address_line' => $address->address_line,
                    'is_default' => $address->is_default,
                ])
                ->values(),
            'districts' => BangladeshDistrict::query()
                ->where('is_active', true)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_bn']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->customer($request);

        if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
            throw ValidationException::withMessages([
                'address_line' => 'You can save up to '.self::MAX_ADDRESSES.' addresses. Please remove one first.',
            ]);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($user, $data): void {
            $isFirst = ! $user->addresses()->exists();
            $makeDefault = $isFirst || $data['is_default'];

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $user->addresses()->create(['is_default' => $makeDefault] + $data);
        });

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $user = $this->customer($request);
        $this->authorizeOwner($user, $address);

        $data = $this->validated($request);

        DB::transaction(function () use ($user, $address, $data): void {
            if ($data['is_default']) {
                $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            } elseif ($address->is_default) {
                // Keep exactly one default: an address cannot un-default itself
                $data['is_default'] = true;
            }

            $address->update($data);
        });

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $user = $this->customer($request);
        $this->authorizeOwner($user, $address);

        DB::transaction(function () use ($user, $address): void {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $user->addresses()->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });

        return back()->with('success', 'Address removed.');
    }

    /**
     * @return array{label: string, recipient_name: string, phone: string, district_id: ?int, address_line: string, is_default: bool}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'max:25'],
            'district_id' => ['required', 'integer', 'exists:bangladesh_districts,id'],
            'address_line' => ['required', 'string', 'min:5', 'max:500'],
            'is_default' => ['boolean'],
        ]);

        $cleanPhone = BangladeshPhoneNumberRule::cleanNumber($validated['phone']);
        if (! BangladeshPhoneNumberRule::isValidFormat($cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid 11-digit Bangladesh mobile number (e.g. 017XXXXXXXX).',
            ]);
        }

        return [
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'phone' => BangladeshPhoneNumberRule::toLocalFormat($cleanPhone),
            'district_id' => (int) $validated['district_id'],
            'address_line' => $validated['address_line'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ];
    }

    private function authorizeOwner(User $user, CustomerAddress $address): void
    {
        if ($address->user_id !== $user->id) {
            abort(404);
        }
    }

    private function customer(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
