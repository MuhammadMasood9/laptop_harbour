<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\User as AppUser;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\PostComment;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\Wishlist;
use App\Notifications\StatusNotification;
use App\Rules\MatchOldPassword;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Helper;
use Illuminate\Support\Str;


class ApiController extends Controller
{





    public function register(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8', // No confirmation in this case
            ]);

            // Insert the user into the database
            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Retrieve the newly created user (optional)
            $user = DB::table('users')->where('id', $userId)->first();

            // Generate a personal access token (requires Eloquent User model)
            $userModel = AppUser::find($userId);
            $token = $userModel->createToken('Personal Access Token')->plainTextToken;

            // Return the success response
            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (Exception $e) {
            // Log the error for debugging purposes
            Log::error('User Registration Error: ' . $e->getMessage());

            // Return a generic error response
            return response()->json([
                'error' => 'An unexpected error occurred. Please try again later.',
                'message' => $e->getMessage(), // Optional: Expose for debugging (remove in production)
            ], 500);
        }
    }
    /**
     * Login user and generate token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        // Find user by email
        $user = AppUser::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401); // 401 Unauthorized
        }

        // Ensure user is active (if you have a 'status' column)
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active.',
            ], 403); // 403 Forbidden
        }

        // Generate a personal access token
        $token = $user->createToken('Personal Access Token')->plainTextToken;

        // Return user and token
        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 200); // 200 OK
    }

    /**
     * Fetch authenticated user details.
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function profile()
    {
        $profile = Auth::user();

        return response()->json([
            'success' => true,
            'data' => $profile,
        ], 200);
    }

    // Update User Profile
    public function profileUpdate(Request $request)
    {
        // Get the currently authenticated user
        $user = Auth::user();

        // Validate the incoming request data
        $validated = $request->validate([
            'name' => 'nullable|string|max:255', // 'name' is optional
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id, // Ensure the email is unique, but not for the current user
            // Add other fields as needed, e.g., phone number, address, etc.
        ]);

        // Update the user's data using the DB facade
        $status = DB::table('users')
            ->where('id', $user->id)
            ->update($validated);

        if ($status) {
            // Fetch the updated user data
            $updatedUser = DB::table('users')->where('id', $user->id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $updatedUser,
            ], 200); // 200 OK status code
        }

        // If the update fails, return an error response
        return response()->json([
            'success' => false,
            'message' => 'Failed to update profile. Please try again.',
        ], 500); // 500 Internal Server Error status code
    }
    // Get Orders
    public function orderIndex()
    {
        $orders = Order::where('user_id', Auth::id())
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ], 200);
    }

    // Delete User Order
    public function userOrderDelete($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if (in_array($order->status, ['process', 'delivered', 'cancel'])) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete this order now',
            ], 400);
        }

        if ($order->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Order successfully deleted',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete order',
        ], 500);
    }

    // Show Order Details
    public function orderShow($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ], 200);
    }

    // Get Product Reviews
    public function productReviewIndex()
    {
        $reviews = ProductReview::where('user_id', Auth::id())->get();

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ], 200);
    }

    // Edit Product Review
    public function productReviewEdit($id)
    {
        $review = ProductReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $review,
        ], 200);
    }

    // Update Product Review
    public function productReviewUpdate(Request $request, $id)
    {
        $review = ProductReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $data = $request->all();

        if ($review->fill($data)->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Review successfully updated',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update review',
        ], 500);
    }

    // Delete Product Review
    public function productReviewDelete($id)
    {
        $review = ProductReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        if ($review->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Review successfully deleted',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete review',
        ], 500);
    }

    // Get User Comments
    public function userComment()
    {
        $comments = PostComment::where('user_id', Auth::id())->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ], 200);
    }

    // Delete User Comment
    public function userCommentDelete($id)
    {
        $comment = PostComment::find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        if ($comment->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment successfully deleted',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete comment',
        ], 500);
    }

    // Edit User Comment
    public function userCommentEdit($id)
    {
        $comment = PostComment::find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $comment,
        ], 200);
    }

    // Update User Comment
    public function userCommentUpdate(Request $request, $id)
    {
        $comment = PostComment::find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        $data = $request->all();

        if ($comment->fill($data)->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment successfully updated',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update comment',
        ], 500);
    }

    // Change User Password
    public function changePassword(Request $request)
    {
        // Validate the request
        $request->validate([
            'current_password' => ['required', new MatchOldPassword], // Custom validation rule for current password
            'new_password' => ['required', 'min:8'], // Validate new password length
            'new_confirm_password' => ['required', 'same:new_password'], // Ensure passwords match
        ]);

        // Get the currently authenticated user
        $user = Auth::user();

        // Update the user's password using the DB facade
        $status = DB::table('users')
            ->where('id', $user->id)
            ->update([
                'password' => Hash::make($request->new_password), // Hash the new password
            ]);

        if ($status) {
            return response()->json([
                'success' => true,
                'message' => 'Password successfully changed',
            ], 200); // 200 OK status code
        }

        // If the update fails, return an error response
        return response()->json([
            'success' => false,
            'message' => 'Failed to change password. Please try again.',
        ], 500); // 500 Internal Server Error status code
    }

    public function wishlist(Request $request)
    {
        if (empty($request->slug)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid product',
            ], 400); // Bad Request
        }

        $product = Product::where('slug', $request->slug)->first();

        if (empty($product)) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404); // Not Found
        }

        $already_wishlist = Wishlist::where('user_id', auth()->user()->id)
            ->where('cart_id', null)
            ->where('product_id', $product->id)
            ->first();

        if ($already_wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'Product already in wishlist',
            ], 409); // Conflict
        }

        // Check if the stock is sufficient
        if ($product->stock < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Stock not sufficient',
            ], 400); // Bad Request
        }

        $wishlist = new Wishlist;
        $wishlist->user_id = auth()->user()->id;
        $wishlist->product_id = $product->id;
        $wishlist->price = $product->price - ($product->price * $product->discount) / 100;
        $wishlist->quantity = 1;
        $wishlist->amount = $wishlist->price * $wishlist->quantity;
        $wishlist->save();

        return response()->json([
            'success' => true,
            'message' => 'Product successfully added to wishlist',
            'wishlist_item' => $wishlist, // Optionally return the added item details
        ], 201); // 201 Created
    }
    public function wishlistDelete(Request $request)
    {
        $wishlist = Wishlist::find($request->id);

        if ($wishlist) {
            $wishlist->delete();
            return response()->json([
                'success' => true,
                'message' => 'Wishlist item successfully removed',
            ], 200); // 200 OK
        }

        return response()->json([
            'success' => false,
            'message' => 'Wishlist item not found',
        ], 404); // Not Found
    }

    public function addToCart(Request $request)
    {
        if (empty($request->slug)) {
            return response()->json(['error' => 'Invalid Product'], 400);
        }

        $product = Product::where('slug', $request->slug)->first();
        if (empty($product)) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $already_cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->where('product_id', $product->id)->first();

        if ($already_cart) {
            $already_cart->quantity += 1;
            $already_cart->amount += $product->price;

            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return response()->json(['error' => 'Stock not sufficient'], 400);
            }

            $already_cart->save();
        } else {
            $cart = new Cart;
            $cart->user_id = auth()->user()->id;
            $cart->product_id = $product->id;
            $cart->price = ($product->price - ($product->price * $product->discount) / 100);
            $cart->quantity = 1;
            $cart->amount = $cart->price * $cart->quantity;

            if ($cart->product->stock < $cart->quantity || $cart->product->stock <= 0) {
                return response()->json(['error' => 'Stock not sufficient'], 400);
            }

            $cart->save();
            Wishlist::where('user_id', auth()->user()->id)->where('cart_id', null)->update(['cart_id' => $cart->id]);
        }

        return response()->json(['success' => 'Product successfully added to cart'], 200);
    }

    // Single product add to cart with quantity
    public function singleAddToCart(Request $request)
    {
        $request->validate([
            'slug' => 'required',
            'quant' => 'required|array',
            'quant.*' => 'required|integer|min:1',
        ]);

        $product = Product::where('slug', $request->slug)->first();
        if (!$product || $product->stock < $request->quant[0]) {
            return response()->json(['error' => 'Invalid Product or Out of Stock'], 400);
        }

        $already_cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->where('product_id', $product->id)->first();

        if ($already_cart) {
            $already_cart->quantity += $request->quant[0];
            $already_cart->amount += $product->price * $request->quant[0];

            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return response()->json(['error' => 'Stock not sufficient'], 400);
            }

            $already_cart->save();
        } else {
            $cart = new Cart;
            $cart->user_id = auth()->user()->id;
            $cart->product_id = $product->id;
            $cart->price = ($product->price - ($product->price * $product->discount) / 100);
            $cart->quantity = $request->quant[0];
            $cart->amount = $cart->price * $request->quant[0];

            if ($cart->product->stock < $cart->quantity || $cart->product->stock <= 0) {
                return response()->json(['error' => 'Stock not sufficient'], 400);
            }

            $cart->save();
        }

        return response()->json(['success' => 'Product successfully added to cart'], 200);
    }

    // Remove product from cart
    public function cartDelete(Request $request)
    {
        $cart = Cart::find($request->id);
        if ($cart && $cart->user_id === auth()->user()->id) {
            $cart->delete();
            return response()->json(['success' => 'Cart item successfully removed'], 200);
        }

        return response()->json(['error' => 'Cart item not found or unauthorized'], 404);
    }

    // Update cart product quantity
    public function cartUpdate(Request $request)
    {
        $request->validate([
            'quant' => 'required|array',
            'quant.*' => 'required|integer|min:1',
            'qty_id' => 'required|array',
            'qty_id.*' => 'required|integer|exists:carts,id',
        ]);

        foreach ($request->quant as $k => $quant) {
            $cart = Cart::find($request->qty_id[$k]);
            if ($cart && $quant > 0) {
                if ($cart->product->stock < $quant) {
                    return response()->json(['error' => 'Out of stock'], 400);
                }

                $cart->quantity = $quant;
                $cart->amount = $cart->product->price * $quant;
                $cart->save();
            } else {
                return response()->json(['error' => 'Invalid cart item'], 400);
            }
        }

        return response()->json(['success' => 'Cart successfully updated'], 200);
    }
    public function productLists(Request $request)
    {
        $products = Product::query();

        if ($request->has('category')) {
            $slug = explode(',', $request->category);
            $cat_ids = Category::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            $products->whereIn('cat_id', $cat_ids);
        }

        if ($request->has('brand')) {
            $slugs = explode(',', $request->brand);
            $brand_ids = Brand::select('id')->whereIn('slug', $slugs)->pluck('id')->toArray();
            $products->whereIn('brand_id', $brand_ids);
        }

        if ($request->has('sortBy')) {
            if ($request->sortBy == 'title') {
                $products->orderBy('title', 'ASC');
            } elseif ($request->sortBy == 'price') {
                $products->orderBy('price', 'ASC');
            }
        }

        if ($request->has('price')) {
            $price = explode('-', $request->price);
            $products->whereBetween('price', $price);
        }

        if ($request->has('show')) {
            $products = $products->paginate($request->show);
        } else {
            $products = $products->paginate(6);
        }

        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return response()->json([
            'products' => $products,
            'recent_products' => $recent_products,
        ]);
    }
    public function productFilter(Request $request)
    {
        $data = $request->all();

        $filters = [];

        if ($request->has('show')) {
            $filters['show'] = $data['show'];
        }

        if ($request->has('sortBy')) {
            $filters['sortBy'] = $data['sortBy'];
        }

        if ($request->has('category')) {
            $filters['category'] = $data['category'];
        }

        if ($request->has('brand')) {
            $filters['brand'] = $data['brand'];
        }

        if ($request->has('price_range')) {
            $filters['price_range'] = $data['price_range'];
        }

        return response()->json([
            'filters' => $filters,
            'redirect_url' => route('product-lists', $filters),
        ]);
    }

    public function productSearch(Request $request)
    {
        $products = Product::where('status', 'active')
            ->where(function ($query) use ($request) {
                $query->orWhere('title', 'like', '%' . $request->search . '%')
                    ->orWhere('slug', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('summary', 'like', '%' . $request->search . '%')
                    ->orWhere('price', 'like', '%' . $request->search . '%');
            })
            ->orderBy('id', 'DESC')
            ->paginate(9);

        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return response()->json([
            'products' => $products,
            'recent_products' => $recent_products,
        ]);
    }
    public function productBrand(Request $request)
    {
        $products = Brand::getProductByBrand($request->slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return response()->json([
            'products' => $products->products,
            'recent_products' => $recent_products,
        ]);
    }

    public function productCat(Request $request)
    {
        $products = Category::getProductByCat($request->slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return response()->json([
            'products' => $products->products,
            'recent_products' => $recent_products,
        ]);
    }
    public function productSubCat(Request $request)
    {
        $products = Category::getProductBySubCat($request->sub_slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return response()->json([
            'products' => $products->sub_products,
            'recent_products' => $recent_products,
        ]);
    }

    public function createReview(Request $request)
{
    // Validate the request data
    $this->validate($request, [
        'rate' => 'required|numeric|min:1',
    ]);

    $product_info = Product::getProductBySlug($request->slug);
    if (!$product_info) {
        return response()->json(['message' => 'Product not found'], 404);
    }

    $data = $request->all();
    $data['product_id'] = $product_info->id;
    $data['user_id'] = $request->user()->id;
    $data['status'] = 'active';

    // Create the product review
    $status = ProductReview::create($data);

    // Notify admin (optional, can be omitted for pure API)
    $user = AppUser::where('role', 'admin')->get();
    $details = [
        'title' => 'New Product Rating!',
        'actionURL' => route('product-detail', $product_info->slug),
        'fas' => 'fa-star',
    ];
    Notification::send($user, new StatusNotification($details));

    if ($status) {
        return response()->json(['message' => 'Thank you for your feedback'], 201);
    } else {
        return response()->json(['message' => 'Something went wrong! Please try again!!'], 500);
    }
}

public function updateReview(Request $request, $id)
{
    $review = ProductReview::find($id);

    if ($review) {
        $data = $request->all();
        $status = $review->fill($data)->update();

        if ($status) {
            return response()->json(['message' => 'Review successfully updated'], 200);
        } else {
            return response()->json(['message' => 'Something went wrong! Please try again!!'], 500);
        }
    } else {
        return response()->json(['message' => 'Review not found'], 404);
    }
}
public function deleteReview($id)
{
    $review = ProductReview::find($id);

    if ($review) {
        $status = $review->delete();

        if ($status) {
            return response()->json(['message' => 'Successfully deleted review'], 200);
        } else {
            return response()->json(['message' => 'Something went wrong! Try again'], 500);
        }
    } else {
        return response()->json(['message' => 'Review not found'], 404);
    }
}


public function createOrder(Request $request)
{
    $this->validate($request, [
        'first_name' => 'string|required',
        'last_name' => 'string|required',
        'address1' => 'string|required',
        'address2' => 'string|nullable',
        'coupon' => 'nullable|numeric',
        'phone' => 'numeric|required',
        'post_code' => 'string|nullable',
        'email' => 'string|required'
    ]);

    if (empty(Cart::where('user_id', auth()->user()->id)->where('order_id', null)->first())) {
        return response()->json(['message' => 'Cart is Empty!'], 400);
    }

    $order = new Order();
    $order_data = $request->all();
    $order_data['order_number'] = 'ORD-' . strtoupper(Str::random(10));
    $order_data['user_id'] = $request->user()->id;
    $order_data['shipping_id'] = $request->shipping;

    $shipping = Shipping::where('id', $order_data['shipping_id'])->pluck('price');
    $order_data['sub_total'] = Helper::totalCartPrice();
    $order_data['quantity'] = Helper::cartCount();

    if (session('coupon')) {
        $order_data['coupon'] = session('coupon')['value'];
    }

    if ($request->shipping) {
        if (session('coupon')) {
            $order_data['total_amount'] = Helper::totalCartPrice() + $shipping[0] - session('coupon')['value'];
        } else {
            $order_data['total_amount'] = Helper::totalCartPrice() + $shipping[0];
        }
    } else {
        if (session('coupon')) {
            $order_data['total_amount'] = Helper::totalCartPrice() - session('coupon')['value'];
        } else {
            $order_data['total_amount'] = Helper::totalCartPrice();
        }
    }

    $order_data['status'] = "new";

    if ($request->payment_method == 'paypal') {
        $order_data['payment_method'] = 'paypal';
        $order_data['payment_status'] = 'paid';
    } else {
        $order_data['payment_method'] = 'cod';
        $order_data['payment_status'] = 'Unpaid';
    }

    $order->fill($order_data);
    $status = $order->save();

    if ($status) {
        Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => $order->id]);

        // Notify admin (optional, can be omitted in an API)
        $users = AppUser::where('role', 'admin')->first();
        $details = [
            'title' => 'New order created',
            'actionURL' => route('order.show', $order->id),
            'fas' => 'fa-file-alt'
        ];
        Notification::send($users, new StatusNotification($details));

        return response()->json([
            'message' => 'Your product has been successfully placed in order',
            'order_id' => $order->id,
            'payment_method' => $order->payment_method
        ], 201);
    }

    return response()->json(['message' => 'Something went wrong! Please try again!'], 500);
}
public function updateOrder(Request $request, $id)
{
    $order = Order::find($id);
    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    $this->validate($request, [
        'status' => 'required|in:new,process,delivered,cancel'
    ]);

    $data = $request->all();

    if ($request->status == 'delivered') {
        foreach ($order->cart as $cart) {
            $product = $cart->product;
            $product->stock -= $cart->quantity;
            $product->save();
        }
    }

    $status = $order->fill($data)->save();

    if ($status) {
        return response()->json(['message' => 'Order successfully updated'], 200);
    } else {
        return response()->json(['message' => 'Error while updating order'], 500);
    }
}
public function deleteOrder($id)
{
    $order = Order::find($id);
    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    $status = $order->delete();

    if ($status) {
        return response()->json(['message' => 'Order successfully deleted'], 200);
    } else {
        return response()->json(['message' => 'Error while deleting order'], 500);
    }
}
public function trackOrder(Request $request)
{
    $order = Order::where('user_id', auth()->user()->id)->where('order_number', $request->order_number)->first();

    if ($order) {
        switch ($order->status) {
            case 'new':
                return response()->json(['message' => 'Your order has been placed. Please wait.'], 200);
            case 'process':
                return response()->json(['message' => 'Your order is under processing. Please wait.'], 200);
            case 'delivered':
                return response()->json(['message' => 'Your order is successfully delivered.'], 200);
            case 'cancel':
                return response()->json(['message' => 'Your order was canceled. Please try again.'], 200);
            default:
                return response()->json(['message' => 'Invalid order status.'], 400);
        }
    }

    return response()->json(['message' => 'Invalid order number. Please try again.'], 404);
}
public function generatePdf(Request $request)
{
    $order = Order::getAllOrder($request->id);
    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    $file_name = $order->order_number . '-' . $order->first_name . '.pdf';
    $pdf = Pdf::loadview('backend.order.pdf', compact('order'));

    return response()->stream(function () use ($pdf) {
        echo $pdf->output();
    }, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $file_name . '"',
    ]);
}


    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request)
    {
        try {
            // Revoke the current access token
            $request->user()->currentAccessToken()->delete();

            // Return a success response
            return response()->json([
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error('Sanctum Logout Error: ' . $e->getMessage());

            // Return an error response
            return response()->json([
                'error' => 'An unexpected error occurred. Please try again later.',
                'message' => $e->getMessage(), // Optional, remove in production
            ], 500);
        }
    }
}
