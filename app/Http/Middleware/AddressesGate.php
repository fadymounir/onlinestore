<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the customer's address book when `customer_addresses_enabled` is off.
 *
 * The switch takes addresses out of the shop entirely — checkout stops asking
 * for one and the shop collects delivery details itself — so leaving the page
 * reachable would let a customer add or edit an address nothing will read.
 * Applied to the routes rather than inside the controller so no action can be
 * added later and quietly miss the check.
 */
class AddressesGate
{
    public const CLOSED_MESSAGE = 'إدارة العناوين متوقفة حالياً. سنتواصل معك لتأكيد عنوان التوصيل.';

    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::customerAddressesEnabled()) {
            return $next($request);
        }

        return redirect()->route('account.orders.index')->with('error', self::CLOSED_MESSAGE);
    }
}
