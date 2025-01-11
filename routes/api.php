<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::post('/login', [ApiController::class, 'login']); // Public route for login
Route::post('/register', [ApiController::class, 'register'])->middleware('throttle:60,1'); // Public route for login
Route::post('/mas', [ApiController::class, 'mas']); // Public route for login

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [ApiController::class, 'user']); // Protected route to fetch user details

    Route::get('/profile', [ApiController::class, 'profile'])->name('api.profile');
    Route::put('/profile', [ApiController::class, 'profileUpdate'])->name('api.profile.update');

    // Order Routes
    Route::get('/orders', [ApiController::class, 'orderIndex'])->name('api.orders.index');
    Route::get('/orders/{id}', [ApiController::class, 'orderShow'])->name('api.orders.show');
    Route::delete('/orders/{id}', [ApiController::class, 'userOrderDelete'])->name('api.orders.delete');

    // Product Review Routes
    Route::get('/reviews', [ApiController::class, 'productReviewIndex'])->name('api.reviews.index');
    Route::get('/reviews/{id}', [ApiController::class, 'productReviewEdit'])->name('api.reviews.edit');
    Route::put('/reviews/{id}', [ApiController::class, 'productReviewUpdate'])->name('api.reviews.update');
    Route::delete('/reviews/{id}', [ApiController::class, 'productReviewDelete'])->name('api.reviews.delete');

    // Comment Routes
    Route::get('/comments', [ApiController::class, 'userComment'])->name('api.comments.index');
    Route::get('/comments/{id}', [ApiController::class, 'userCommentEdit'])->name('api.comments.edit');
    Route::put('/comments/{id}', [ApiController::class, 'userCommentUpdate'])->name('api.comments.update');
    Route::delete('/comments/{id}', [ApiController::class, 'userCommentDelete'])->name('api.comments.delete');

    // Password Change Route
    Route::post('/change-password', [ApiController::class, 'changePassword'])->name('api.change-password');

    Route::post('wishlist', [ApiController::class, 'wishlist']);

    // Route for deleting a product from the wishlist
    Route::delete('wishlist/{id}', [ApiController::class, 'wishlistDelete']);

    Route::post('cart', [ApiController::class, 'addToCart']);

    // Add a single product with quantity to cart
    Route::post('cart/single', [ApiController::class, 'singleAddToCart']);

    // Delete product from cart
    Route::delete('cart/{id}', [ApiController::class, 'cartDelete']);

    // Update cart item quantity
    Route::put('cart', [ApiController::class, 'cartUpdate']);

    // Product

    Route::get('products', [ApiController::class, 'productLists']);
    Route::post('products/filter', [ApiController::class, 'productFilter']);
    Route::get('products/search', [ApiController::class, 'productSearch']);
    Route::get('products/brand/{slug}', [ApiController::class, 'productBrand']);
    Route::get('products/category/{slug}', [ApiController::class, 'productCat']);
    Route::get('products/sub-category/{sub_slug}', [ApiController::class, 'productSubCat']);

    //review
    Route::post('reviews', [ApiController::class, 'createReview']); // Create review
    Route::put('reviews/{id}', [ApiController::class, 'updateReview']); // Update review
    Route::delete('reviews/{id}', [ApiController::class, 'deleteReview']); // Delete review

    //order
    Route::post('orders', [ApiController::class, 'createOrder']); // Create order
    Route::put('orders/{id}', [ApiController::class, 'updateOrder']); // Update order
    Route::delete('orders/{id}', [ApiController::class, 'deleteOrder']); // Delete order
    Route::post('orders/track', [ApiController::class, 'trackOrder']); // Track order
    Route::get('orders/{id}/pdf', [ApiController::class, 'generatePdf']); // Generate PDF


    Route::post('/logout', [ApiController::class, 'logout']); // Protected route for logout
});
