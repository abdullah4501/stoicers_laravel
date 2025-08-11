<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CustomerController extends Controller
{
    // POST /api/customer/register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            'address' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // hashed by casts()
            'address' => $data['address'] ?? null,
            'area' => $data['area'] ?? null,
            'city' => $data['city'] ?? null,
        ]);

        $token = $customer->createToken('api')->plainTextToken;

        return response()->json([
            'customer' => $customer,
            'token' => $token,
        ], 201);
    }

    // POST /api/customer/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('email', $data['email'])->first();
        if (!$customer || !Hash::check($data['password'], $customer->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $token = $customer->createToken('api')->plainTextToken;

        return response()->json([
            'customer' => $customer,
            'token' => $token,
        ]);
    }

    // GET /api/customer/me  (Authorization: Bearer <token>)
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // POST /api/customer/logout (Authorization: Bearer <token>)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}