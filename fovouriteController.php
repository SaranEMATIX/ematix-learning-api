<?php


namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{


    public function toggleFavorite(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        $favorite = Favorite::where('user_id', $request->user_id)
            ->where('subcategory_id', $request->subcategory_id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json([
                'status' => true,
                'message' => 'Removed from favorites',
            ]);
        } else {
            Favorite::create($request->only('user_id', 'subcategory_id'));
            return response()->json([
                'status' => true,
                'message' => 'Added to favorites',
            ]);
        }
    }

    public function getFavorites(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $favorites = Favorite::with('subcategory.category')
            ->where('user_id', $request->user_id)
            ->get()
            ->map(function ($fav) {
                return [
                    'subcategory_id'   => $fav->subcategory->id,
                    'subcategory_name' => $fav->subcategory->name,
                    'category_id'      => $fav->subcategory->category->id,
                    'category_name'    => $fav->subcategory->category->name,
                    'rate'             => $fav->subcategory->rate,
                    'image'            => $fav->subcategory->images,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Favorites fetched successfully',
            'data' => $favorites,
        ]);
    }
}
