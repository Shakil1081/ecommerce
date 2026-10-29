<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Actions\AddToCartAction;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Cart\Actions\RemoveFromCartAction;
use App\Modules\Cart\Actions\UpdateCartItemQuantityAction;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CartController extends Controller
{
    /**
     * Display the cart contents or return JSON for slide-out drawer.
     */
    public function index(Request $request, GetActiveCartAction $getCart): Response|JsonResponse
    {
        $token = $request->cookie('ecommerce_guest_token') ?: $request->header('X-Guest-Token');
        $cart = $getCart($request->user(), is_string($token) ? $token : null);
        $cart->loadMissing('items.variant.product.media');

        if ($request->wantsJson() || $request->is('cart/drawer')) {
            return (new CartResource($cart))
                ->response()
                ->setStatusCode(200)
                ->withCookie(
                    cookie('ecommerce_guest_token', $cart->guest_token, 60 * 24 * 30)
                );
        }

        return Inertia::render('Cart/Index', [
            'cart' => new CartResource($cart),
        ]);
    }

    /**
     * Add a product variant item to the cart.
     */
    public function addItem(
        Request $request,
        GetActiveCartAction $getCart,
        AddToCartAction $addAction
    ): JsonResponse {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $token = $request->cookie('ecommerce_guest_token') ?: $request->header('X-Guest-Token');
        $cart = $getCart($request->user(), is_string($token) ? $token : null);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);
        $quantity = (int) ($validated['quantity'] ?? 1);

        $updatedCart = $addAction($cart, $variant, $quantity);
        $updatedCart->loadMissing('items.variant.product.media');

        return (new CartResource($updatedCart))
            ->response()
            ->setStatusCode(200)
            ->withCookie(
                cookie('ecommerce_guest_token', $cart->guest_token, 60 * 24 * 30)
            );
    }

    /**
     * Update an item's quantity in the cart.
     */
    public function updateQuantity(
        Request $request,
        string $variantId,
        GetActiveCartAction $getCart,
        UpdateCartItemQuantityAction $updateAction
    ): JsonResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer'],
        ]);

        $token = $request->cookie('ecommerce_guest_token') ?: $request->header('X-Guest-Token');
        $cart = $getCart($request->user(), is_string($token) ? $token : null);

        $updatedCart = $updateAction($cart, $variantId, (int) $validated['quantity']);
        $updatedCart->loadMissing('items.variant.product.media');

        return (new CartResource($updatedCart))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(
        Request $request,
        string $variantId,
        GetActiveCartAction $getCart,
        RemoveFromCartAction $removeAction
    ): JsonResponse {
        $token = $request->cookie('ecommerce_guest_token') ?: $request->header('X-Guest-Token');
        $cart = $getCart($request->user(), is_string($token) ? $token : null);

        $updatedCart = $removeAction($cart, $variantId);
        $updatedCart->loadMissing('items.variant.product.media');

        return (new CartResource($updatedCart))
            ->response()
            ->setStatusCode(200);
    }
}
