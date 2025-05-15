<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;

class CartController extends Controller
{
    public function toggleCart(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        $exists = Cart::where('user_id', $request->user_id)
            ->where('subcategory_id', $request->subcategory_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Item already in cart',
            ], 409);
        }

        Cart::create($request->only('user_id', 'subcategory_id'));

        return response()->json([
            'status' => true,
            'message' => 'Item added to cart successfully',
        ]);
    }

    public function getCartItems(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $items = Cart::with('subcategory.category')
            ->where('user_id', $request->user_id)
            ->get()
            ->map(function ($item) {
                return [
                    'subcategory_id'   => $item->subcategory->id,
                    'subcategory_name' => $item->subcategory->name,
                    'category_id'      => $item->subcategory->category->id,
                    'category_name'    => $item->subcategory->category->name,
                    'rate'             => $item->subcategory->rate,
                    'image'            => $item->subcategory->images,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Cart items fetched successfully',
            'data' => $items,
        ]);
    }

    public function removeCartItem(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        $item = Cart::where('user_id', $request->user_id)
            ->where('subcategory_id', $request->subcategory_id)
            ->first();

        if (!$item) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found in cart.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'status' => true,
            'message' => 'Item removed from cart successfully.',
        ]);
    }
}
