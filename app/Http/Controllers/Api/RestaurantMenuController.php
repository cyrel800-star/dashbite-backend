<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantMenuController extends Controller
{
    /**
     * Get the authenticated restaurant's own menu.
     * Protected: auth:sanctum, role:restaurant, approved.
     */
    private function ownRestaurantId(Request $request): int
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_if($restaurantId === null, 403, 'No restaurant linked to this account.');

        return $restaurantId;
    }

    public function index(Request $request)
    {
        $items = MenuItem::where('restaurant_id', $this->ownRestaurantId($request))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $items,
            'meta' => ['count' => $items->count()],
        ]);
    }

    public function store(Request $request)
    {
        $restaurantId = $this->ownRestaurantId($request);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'price'        => ['required', 'numeric', 'min:0.01'],
            'is_available' => ['boolean'],
        ]);

        $item = MenuItem::create([
            'restaurant_id' => $restaurantId,
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'price'         => $data['price'],
            'is_available'  => $data['is_available'] ?? true,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, int $id)
    {
        $restaurantId = $this->ownRestaurantId($request);

        $item = MenuItem::where('restaurant_id', $restaurantId)->find($id);

        if (! $item) {
            return response()->json(['error' => 'menu_item_not_found'], 404);
        }

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'price'        => ['sometimes', 'numeric', 'min:0.01'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $item->update($data);

        return response()->json(['data' => $item->fresh()]);
    }

    public function toggleAvailability(Request $request, int $id)
    {
        $restaurantId = $this->ownRestaurantId($request);

        $item = MenuItem::where('restaurant_id', $restaurantId)->find($id);

        if (! $item) {
            return response()->json(['error' => 'menu_item_not_found'], 404);
        }

        $item->update(['is_available' => ! $item->is_available]);

        return response()->json([
            'data'    => $item->fresh(),
            'message' => $item->is_available
                ? 'Item marked as available.'
                : 'Item marked as out of stock.',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $restaurantId = $this->ownRestaurantId($request);

        $item = MenuItem::where('restaurant_id', $restaurantId)->find($id);

        if (! $item) {
            return response()->json(['error' => 'menu_item_not_found'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Menu item deleted.']);
    }
}