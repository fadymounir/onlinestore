<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(): View
    {
        return view('account.addresses', [
            'addresses' => Auth::user()->addresses()->with('governorate', 'city')->get(),
            'governorates' => Governorate::active()->orderBy('sort_order')->get(),
            'cities' => City::active()->orderBy('name')->get()->groupBy('governorate_id'),
            'editing' => null,
        ]);
    }

    public function edit(Address $address): View
    {
        $this->authorizeAddress($address);

        return view('account.addresses', [
            'addresses' => Auth::user()->addresses()->with('governorate', 'city')->get(),
            'governorates' => Governorate::active()->orderBy('sort_order')->get(),
            'cities' => City::active()->orderBy('name')->get()->groupBy('governorate_id'),
            'editing' => $address,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $address = Auth::user()->addresses()->create($this->validated($request));

        $this->syncDefault($address, $request->boolean('is_default'));

        return redirect()->route('account.addresses.index')->with('success', 'تم حفظ العنوان.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $address->update($this->validated($request));

        $this->syncDefault($address, $request->boolean('is_default'));

        return redirect()->route('account.addresses.index')->with('success', 'تم تحديث العنوان.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $address->delete();

        return back()->with('success', 'تم حذف العنوان.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[0-2,5]\d{8}$/'],
            'alternate_phone' => ['nullable', 'string', 'regex:/^01[0-2,5]\d{8}$/'],
            'governorate_id' => ['required', 'exists:governorates,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'street' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'apartment' => ['nullable', 'string', 'max:50'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'أدخل رقم موبايل مصري صحيح.',
            'alternate_phone.regex' => 'أدخل رقم موبايل مصري صحيح.',
            'governorate_id.required' => 'اختر المحافظة.',
        ]);
    }

    /** Exactly one default address per customer. */
    private function syncDefault(Address $address, bool $isDefault): void
    {
        if (! $isDefault) {
            return;
        }

        Auth::user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    private function authorizeAddress(Address $address): void
    {
        abort_unless($address->user_id === Auth::id(), 404);
    }
}
