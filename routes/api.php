<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

Route::get('image-base64/{image}', function ($image) {
    $imagePath = storage_path('app/public/users/' . $image);

    if (file_exists($imagePath)) {
        $imageData = file_get_contents($imagePath);
        $base64 = base64_encode($imageData);

        return response()->json(['image' => 'data:image/jpeg;base64,' . $base64]);
    }

    return response()->json(['error' => 'Image not found'], 404);
});

RateLimiter::for('api', function () {
    return Limit::perMinute(100); // Adjust the limit here
});


Route::post('/login', [ApiController::class, 'login']); // Public route for login


Route::post('/register', [ApiController::class, 'register']); // Public route for login

Route::get('/shipment', [ApiController::class, 'shipment']);
Route::get('/user', [ApiController::class, 'user']); // Protected route to fetch user details
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/profile', [ApiController::class, 'profile'])->name('api.profile');
    Route::put('/profileUpdate', [ApiController::class, 'profileUpdate']);

    // Order Routes
    Route::get('/orders', [ApiController::class, 'orderIndex'])->name('api.orders.index');
    Route::get('/orders/{id}', [ApiController::class, 'orderShow'])->name('api.orders.show');
    Route::delete('/orders/{id}', [ApiController::class, 'userOrderDelete'])->name('api.orders.delete');

    // Product Review Routes
    Route::get('/getreviews', [ApiController::class, 'productReviewIndex'])->name('api.reviews.index');

    // Comment Routes
    Route::get('/comments', [ApiController::class, 'userComment'])->name('api.comments.index');
    Route::get('/comments/{id}', [ApiController::class, 'userCommentEdit'])->name('api.comments.edit');
    Route::put('/comments/{id}', [ApiController::class, 'userCommentUpdate'])->name('api.comments.update');
    Route::delete('/comments/{id}', [ApiController::class, 'userCommentDelete'])->name('api.comments.delete');

    // Password Change Route
    Route::post('/change-password', [ApiController::class, 'changePassword'])->name('api.change-password');

    Route::post('wishlist', [ApiController::class, 'wishlist']);

    // Route for deleting a product from the wishlist
    Route::delete('wishlist/delete', [ApiController::class, 'wishlistDelete']);

    Route::post('cart', [ApiController::class, 'addToCart']);
    Route::get('allcart', [ApiController::class, 'AllCart']);
    // Add a single product with quantity to cart
    Route::post('cart/single', [ApiController::class, 'singleAddToCart']);

    // Delete product from cart
    Route::delete('cart/delete', [ApiController::class, 'cartDelete']);

    // Update cart item quantity
    Route::put('cart/update', [ApiController::class, 'cartUpdate']);

    // Product
    Route::get('allwishlist', [ApiController::class, 'allwishlist']);
    Route::get('products', [ApiController::class, 'productLists']);
    Route::get('products/SearchAndFilter', [ApiController::class, 'productSearchAndFilter']);

    Route::get('products/brand', [ApiController::class, 'productBrand']);
    Route::get('products/category', [ApiController::class, 'productCat']);

    //review
    Route::post('reviews', [ApiController::class, 'createReview']); // Create review
    // Route::put('reviews/{id}', [ApiController::class, 'updateReview']); // Update review
    // Route::delete('reviews/{id}', [ApiController::class, 'deleteReview']); // Delete review

    //order
    Route::post('create/orders', [ApiController::class, 'createOrder']); // Create order
    Route::put('orders/{id}', [ApiController::class, 'updateOrder']); // Update order
    Route::delete('orders/{id}', [ApiController::class, 'deleteOrder']); // Delete order
    Route::post('orders/track', [ApiController::class, 'trackOrder']); // Track order
    Route::get('orders/{id}/pdf', [ApiController::class, 'generatePdf']); // Generate PDF


    Route::post('/logout', [ApiController::class, 'logout']); // Protected route for logout
});
