<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));

        $products = Cache::remember("products:page:{$page}", 600, function () {
            return Product::paginate(20);
        });
        return $products;
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request)
    {
        $product  = Product::create($request->validated());

        // Clear outdated product list.
        Cache::forget('products:all');

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $product)
    {
        $productData  = Cache::remember(
            "product:{$product}",
            600,
            fn() => Product::findOrFail($product)
        );

        return $productData ;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductUpdateRequest $request, Product $product)
    {
        // Clear outdated product list.
        Cache::forget('products:all');

        $product->update($request->validated());
        return $product;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        // Clear outdated product list.
        Cache::forget('products:all');

        return response()->json(['msg' => 'Deleted Successfully']);
    }
}
