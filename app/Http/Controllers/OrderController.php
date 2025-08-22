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

        $orders = Order::with('items.product')
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

       return response()->json($order->load('items.product'));
    }

    public function store(Request $request)
    {
        // Validate customer and cart items
        $request->validate([
            'items'   => 'required|array|min:1',
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'area'    => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:255',
        ]);

        $items = $request->input('items');
        $authCustomer = $request->user('customer-api');

        // Take customer info either from input or logged-in customer
        $snapName    = $request->input('name')    ?? $authCustomer?->name;
        $snapEmail   = $request->input('email')   ?? $authCustomer?->email;
        $snapPhone   = $request->input('phone')   ?? $authCustomer?->phone;
        $snapAddress = $request->input('address') ?? $authCustomer?->address;
        $snapArea    = $request->input('area')    ?? $authCustomer?->area;
        $snapCity    = $request->input('city')    ?? $authCustomer?->city;

        // Create the order with actual info!
        $order = Order::create([
            'customer_id'     => $authCustomer?->id,
            'order_number'    => $this->generateUniqueOrderNumber(),
            'status'          => 'pending',
            'payment_status'  => 'unpaid',
            'customer_name'   => $snapName,
            'customer_email'  => $snapEmail,
            'customer_phone'  => $snapPhone,
            'customer_address' => $snapAddress,
            'customer_area'   => $snapArea,
            'customer_city'   => $snapCity,
            // 'total_price' will be updated below
        ]);

        $grandTotal = 0;

        foreach ($items as $cartItem) {
            $product = Product::findOrFail($cartItem['product_id']);
            $quantity = max(1, intval($cartItem['quantity']));
            $orderItem = $order->items()->create([
                'product_id' => $product->id,
                'quantity'   => $quantity,
                'unit_price' => $product->price,
                'total_price' => $product->price * $quantity,
            ]);
            $grandTotal += $orderItem->total_price;
        }

        $order->update(['total_price' => $grandTotal]);

        return response()->json($order->load('items.product'), 201);
    }


    public function update(Request $request, Order $order)
    {
        $customer = $request->user('customer-api');

        if ($order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'status'         => ['sometimes', 'string', 'in:pending,paid,shipped,delivered,cancelled'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid,refunded'],
            // (Optional: add fields if you want to allow customer info to be updated)
        ]);

        if (isset($data['status'])) {
            $order->status = $data['status'];
        }
        if (isset($data['payment_status'])) {
            $order->payment_status = $data['payment_status'];
        }

        $order->save();

        // Return order with all items and their products
        return response()->json($order->load('items.product'));
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
