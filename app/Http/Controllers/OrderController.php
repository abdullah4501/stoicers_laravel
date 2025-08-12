<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * List all orders for the logged-in customer
     */
    public function index(Request $request)
    {
        $customer = $request->user('customer-api');

        $orders = Order::with('product')
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Show a single order (only if it belongs to the logged-in customer)
     */
    public function show(Request $request, Order $order)
    {
        $customer = $request->user('customer-api');

        if ($order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load('product'));
    }

    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        // If logged in via customer-api, this returns the Customer model; otherwise null
        $authCustomer = $request->user('customer-api');

        // Trick to use conditional validation: add a synthetic flag to the request
        $request->merge(['_auth_customer' => $authCustomer?->id]);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['nullable', 'integer', 'min:1'],

            // If not authenticated, these are required; if authenticated, optional (can override)
            'name'       => ['required_without:_auth_customer', 'nullable', 'string', 'max:255'],
            'email'      => ['required_without:_auth_customer', 'nullable', 'email', 'max:255'],
            'phone'      => ['required_without:_auth_customer', 'nullable', 'string', 'max:20'],
            'address'    => ['required_without:_auth_customer', 'nullable', 'string', 'max:500'],
            'area'       => ['required_without:_auth_customer', 'nullable', 'string', 'max:255'],
            'city'       => ['required_without:_auth_customer', 'nullable', 'string', 'max:255'],
        ]);

        $product  = \App\Models\Product::findOrFail($data['product_id']);
        $quantity = $data['quantity'] ?? 1;

        // If you later add price on Product, use it here; for now keep 0 as earlier
        $unitPrice  = 0;
        $totalPrice = $unitPrice * $quantity;

        // Snapshot details: allow overrides even if authenticated
        $snapName    = $data['name']    ?? $authCustomer?->name;
        $snapEmail   = $data['email']   ?? $authCustomer?->email;
        $snapPhone   = $data['phone']   ?? $authCustomer?->phone;
        $snapAddress = $data['address'] ?? $authCustomer?->address;
        $snapArea    = $data['area']    ?? $authCustomer?->area;
        $snapCity    = $data['city']    ?? $authCustomer?->city;

        // Safety: at least ensure we have a name/phone/email snapshot
        if (!$authCustomer && (!$snapName || !$snapEmail || !$snapPhone)) {
            return response()->json(['message' => 'Name, email and phone are required for guest checkout.'], 422);
        }

        $order = \App\Models\Order::create([
            'customer_id'     => $authCustomer?->id,  // null for guests
            'product_id'      => $product->id,
            'order_number'    => $this->generateUniqueOrderNumber(),
            'quantity'        => $quantity,
            'unit_price'      => $unitPrice,
            'total_price'     => $totalPrice,
            'status'          => 'pending',
            'payment_status'  => 'unpaid',

            // snapshot fields
            'customer_name'   => $snapName,
            'customer_email'  => $snapEmail,
            'customer_phone'  => $snapPhone,
            'customer_address'=> $snapAddress,
            'customer_area'   => $snapArea,
            'customer_city'   => $snapCity,
        ]);

        return response()->json($order->load('product'), 201);
    }
    public function update(Request $request, Order $order)
    {
        $customer = $request->user('customer-api');

        if ($order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'quantity'       => ['sometimes', 'integer', 'min:1'],
            'status'         => ['sometimes', 'string', 'in:pending,paid,shipped,delivered,cancelled'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid,refunded'],
        ]);

        if (isset($data['quantity'])) {
            $order->quantity = $data['quantity'];
            $order->total_price = $order->unit_price * $data['quantity'];
        }

        if (isset($data['status'])) {
            $order->status = $data['status'];
        }

        if (isset($data['payment_status'])) {
            $order->payment_status = $data['payment_status'];
        }

        $order->save();

        return response()->json($order->load('product'));
    }

    /**
     * Delete an order (only if it belongs to the logged-in customer)
     */
    public function destroy(Request $request, Order $order)
    {
        $customer = $request->user('customer-api');

        if ($order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }

    /**
     * Generate a unique order number
     */
    private function generateUniqueOrderNumber(): string
    {
        do {
            $number = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
