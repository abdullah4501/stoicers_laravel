<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        $products = Product::latest()->paginate(12);
        return response()->json($products);
    }

    public function show($slugOrId)
    {
        $product = \App\Models\Product::where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->firstOrFail();
        return response()->json($product);
    }


    // POST /api/products  (protected)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'slug'            => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description'     => ['nullable', 'string'],
            'featured_image'  => ['nullable', 'image', 'max:5120'], // 5MB
            'images.*'        => ['nullable', 'image', 'max:5120'],
        ]);

        // store featured image
        $featuredPath = null;
        if ($request->hasFile('featured_image')) {
            $featuredPath = $request->file('featured_image')->store('products', 'public');
        }

        // store gallery images
        $gallery = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $gallery[] = $img->store('products', 'public');
            }
        }

        $product = Product::create([
            'name'           => $data['name'],
            'slug'           => $data['slug'] ?? null,  // model will generate if null
            'description'    => $data['description'] ?? null,
            'featured_image' => $featuredPath,
            'images'         => $gallery,
        ]);

        return response()->json($product, 201);
    }

    // PUT /api/products/{product}  (protected)
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'slug'            => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'description'     => ['sometimes', 'nullable', 'string'],
            'featured_image'  => ['sometimes', 'nullable', 'image', 'max:5120'],
            'images.*'        => ['sometimes', 'nullable', 'image', 'max:5120'],
            // Optional: send an array of image paths to remove from gallery
            'remove_images'   => ['sometimes', 'array'],
            'remove_images.*' => ['string'],
            // Optional: set featured_image to null
            'remove_featured' => ['sometimes', 'boolean'],
        ]);

        // Update simple fields
        if (array_key_exists('name', $data))         $product->name = $data['name'];
        if (array_key_exists('description', $data))  $product->description = $data['description'];
        if (array_key_exists('slug', $data))         $product->slug = $data['slug'] ?: Str::slug($product->name);

        // Handle featured image replacement/removal
        if (array_key_exists('remove_featured', $data) && $data['remove_featured'] && $product->featured_image) {
            Storage::disk('public')->delete($product->featured_image);
            $product->featured_image = null;
        }
        if ($request->hasFile('featured_image')) {
            if ($product->featured_image) {
                Storage::disk('public')->delete($product->featured_image);
            }
            $product->featured_image = $request->file('featured_image')->store('products', 'public');
        }

        // Handle gallery removals
        $current = collect($product->images ?? []);
        if (!empty($data['remove_images'])) {
            foreach ($data['remove_images'] as $path) {
                if ($current->contains($path)) {
                    Storage::disk('public')->delete($path);
                    $current = $current->reject(fn ($p) => $p === $path);
                }
            }
        }

        // Handle gallery additions
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $current->push($img->store('products', 'public'));
            }
        }

        $product->images = $current->values()->all();
        $product->save();

        return response()->json($product);
    }

    // DELETE /api/products/{product}  (protected)
    public function destroy(Product $product)
    {
        // delete files
        if ($product->featured_image) {
            Storage::disk('public')->delete($product->featured_image);
        }
        foreach ($product->images ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    
}
