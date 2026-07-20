<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with([
            'createdBy',
            'updatedBy',
        ]);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $products = $request->filled('limit')
            ? $query->paginate($request->integer('limit'))
            : $query->get();

        return ProductResource::collection($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string', 'min:3'],
            'category' => ['required', 'string'],
            'price' => ['required', 'min:0'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp']
        ]);

        $imagePaths = [];
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            if (!is_array($images)) {
                $images = [$images];
            }
            foreach ($images as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
        }

        $product = new Product();
        $product->title = $request->input('title');
        $product->category = $request->input('category');
        $product->description = $request->input('description');
        $product->price = $request->input('price');
        $product->images = $imagePaths;
        $product->created_by_id = $request->user()->id;
        $product->save();

        return new ProductResource($product)->additional([
            'status' => true,
            'message' => 'Product created successfuly'
        ]);
    }

    public function show(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }
        return new ProductResource($product);
    }

    public function update(Request $request, string $id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            $request->validate([
                'title' => ['sometimes', 'string', 'min:3', 'max:255'],
                'category' => ['sometimes', 'string', 'max:100'],
                'price' => ['sometimes', 'numeric', 'min:0'],
                'images.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            ]);

            if ($request->hasFile('images')) {

                if ($product->images) {
                    foreach ($product->images as $oldImage) {
                        if (Storage::disk('public')->exists($oldImage)) {
                            Storage::disk('public')->delete($oldImage);
                        }
                    }
                }

                $images = $request->file('images');
                if (!is_array($images)) {
                    $images = [$images];
                }

                $newImages = [];
                foreach ($images as $image) {
                    $path = $image->store('products', 'public');
                    $newImages[] = $path;
                }
                $product->images = $newImages;
            }

            $product->fill($request->only([
                'title',
                'category',
                'description',
                'price',
                'stock'
            ]));

            $product->updated_by_id = $request->user()->id;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product)
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        if ($product->images) {
            foreach ($product->images as $image) {
                if (Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
            }
        }

        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ]);

    }
}
