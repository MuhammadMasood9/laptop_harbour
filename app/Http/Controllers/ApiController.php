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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class ApiController extends Controller
{



    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);
    
        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422); // Unprocessable Entity
        }

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
   
    /**
     * Fetch authenticated user details.
     */
    public function user(Request $request)

    {
        
        $user = DB::table('users')->get();
     
       
        return response()->json($user);
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
        
// Validation
$this->validate($request, [
    'name' => 'nullable|string|max:30',
    'email' => 'nullable|string|email',
    'password' => 'nullable|string|min:6',
   
    'status' => 'nullable|in:active,inactive',
    'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
]);

// Initialize the data array from the request, excluding the photo initially
$data = $request->only(['name', 'email', 'password', 'photo', 'status']);

// If a new photo is uploaded
if ($request->hasFile('photo')) {
    // Delete old photo if exists
    if ($user->photo && file_exists(public_path($user->photo))) {
        unlink(public_path($user->photo)); // Delete old photo
    }

    // Define the directory path
    $directoryPath = public_path('storage/users');

    // Create the directory if it doesn't exist
    if (!file_exists($directoryPath)) {
        mkdir($directoryPath, 0777, true); // Create folder with permissions
    }

    // Generate a unique file name for the new photo
    $photoName = time() . '_' . $request->file('photo')->getClientOriginalName();

    // Move the uploaded photo to the directory
    $request->file('photo')->move($directoryPath, $photoName);

    // Save the new photo path in the data array
    $data['photo'] = 'storage/users/' . $photoName;
}

// If password is provided, hash it
if ($request->has('password')) {
    $data['password'] = Hash::make($request->password);
}

// Update the user with the data
$status = $user->update($data);  // The `update()` method should work here

// Return a JSON response
if ($status) {
    return response()->json([
        'success' => true,
        'message' => 'User updated successfully',
        'user' => $user
    ], 200);
} else {
    return response()->json([
        'success' => false,
        'message' => 'Error occurred while updating user'
    ], 400);
}

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
    public function productReviewIndex(Request $request)
    {
        $reviews = ProductReview::where('product_id', $request->slug)->get();

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ], 200);
    }

    // Edit Product Review


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
        // Use Validator::make() to handle validation
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', new MatchOldPassword],
            'new_password' => ['required', 'min:8'],
            'new_confirm_password' => ['required', 'same:new_password'],
        ]);
    
        // If validation fails, return validation errors
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(), // Return validation errors
            ], 422);
        }
    
        try {
            $user = Auth::user();
    
            // Check if the current password matches
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }
    
            // Update password in the database
            $status = DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make($request->new_password),
                ]);
    
            // Check if the update was successful
            if ($status) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password successfully changed',
                ], 200);
            }
    
            // If updating the password failed
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            // Catch any exception that occurs during the password change process
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
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
        $valid = $request->validate([
            'id' => 'required|exists:wishlists,id',
        ]);
    
        if ($valid) {
            // Use the Wishlist model to find and delete the record
            $wishlist = Wishlist::find($request->id);
    
            if ($wishlist) {
                $wishlist->delete(); // Delete using the Eloquent model
                return response()->json([
                    'success' => true,
                    'message' => 'Wishlist item successfully removed',
                ], 200);
            }
        }
    
        return response()->json([
            'success' => false,
            'message' => 'Wishlist item not found',
        ], 404);
    }
    

    public function addToCart(Request $request)
    {
        if (empty($request->slug)) {
            return response()->json(['error' => 'Invalid Product'], 400);
        }

        $product = Product::where('id', $request->slug)->first();
        if (empty($product)) {
            return response()->json(['error' => 'Product not found'], 400);
        }

        $already_cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->where('product_id', $product->id)->first();

        if ($already_cart) {
            $already_cart->quantity += 1;
            $already_cart->amount += $product->price;

            if ($already_cart->product->stock < $already_cart->quantity || $already_cart->product->stock <= 0) {
                return response()->json(['success' => 'Stock not sufficient'], 200);
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
                return response()->json(['success' => 'Stock not sufficient'], 400);
            }

            $cart->save();
            Wishlist::where('user_id', auth()->user()->id)->where('cart_id', null)->update(['cart_id' => $cart->id]);
        }

        return response()->json(['success' => 'Product successfully added to cart'], 200);
    }
    public function allwishlist(Request $request)
    {
       

        $already_cart = Wishlist::with('product')->where('user_id', auth()->user()->id)->get();

        if(!$already_cart){
            return response()->json(['wishlist' => "No Cart Found",], 200);
        }

        return response()->json(['wishlist' => $already_cart,], 200);
    }

    public function AllCart(Request $request)
    {
       

        $already_cart = Cart::with('product')->where('order_id',null)->where('user_id', auth()->user()->id)->get();

        if(!$already_cart){
            return response()->json(['cart' => "No Cart Found",], 200);
        }

        return response()->json(['cart' => $already_cart,], 200);
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
            'quant' => 'required|array|min:1', // Ensure it's a non-empty array
            'quant.*' => 'required|integer|min:1', // Each quantity must be an integer and at least 1
            'qty_id' => 'required|array|min:1', // Ensure it's a non-empty array
            'qty_id.*' => 'required|integer|exists:carts,id', // Each ID must exist in the 'carts' table
        ]);
    
        foreach ($request->quant as $key => $quantity) {
            $cartId = $request->qty_id[$key] ?? null; // Get the corresponding cart ID
    
            // Ensure the cart exists and quantity is valid
            $cart = Cart::find($cartId);
            if ($cart) {
                if ($cart->product->stock < $quantity) {
                    return response()->json(['error' => 'Out of stock for product: ' . $cart->product->title], 400);
                }
    
                // Update the cart item
                $cart->quantity = $quantity;
                $cart->amount = $cart->product->price * $quantity;
                $cart->save();
            } else {
                return response()->json(['error' => 'Invalid cart item or cart ID'], 400);
            }
        }
    
        return response()->json(['success' => 'Cart successfully updated'], 200);
    }
    
    public function productLists(Request $request)
    {
        // Validate the incoming request to ensure the 'id' is provided
        $request->validate([
            'id' => 'required|integer|exists:products,id', // Validate that 'id' is an integer and exists in the products table
        ]);
    
        // Retrieve the product by its ID
        $product = Product::with(['cat_info', 'brand'])  // Assuming the relationship is defined
                          ->find($request->id);
    
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }
    
        // Return the product data with its associated category and brand details
        return response()->json([
            'product' => $product,
            
        ]);
    }
    
    public function productSearchAndFilter(Request $request)
    {
        $data = $request->all();
    
        // Start with a query to get all products
        $query = Product::where('status', 'active');
    
        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('cat_id', $data['category']);
        }
    
        // Filter by brand if provided
        if ($request->has('brand')) {
            $query->where('brand_id', $data['brand']);
        }
    
        // Filter by price range if provided
        if ($request->has('price_range')) {
            $priceRange = explode('-', $data['price_range']);
            if (count($priceRange) == 2) {
                $query->whereBetween('price', [$priceRange[0], $priceRange[1]]);
            }
        }
    
        // Apply search term if provided
        if ($request->has('search') && !empty($data['search'])) {
            $query->where(function ($query) use ($data) {
                $query->orWhere('title', 'like', '%' . $data['search'] . '%')
                    ->orWhere('slug', 'like', '%' . $data['search'] . '%')
                    ->orWhere('description', 'like', '%' . $data['search'] . '%')
                    ->orWhere('summary', 'like', '%' . $data['search'] . '%')
                    ->orWhere('price', 'like', '%' . $data['search'] . '%');
            });
        }
    
        // Apply sorting if 'sortBy' is provided
        if ($request->has('sortBy')) {
            switch ($data['sortBy']) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                default:
                    break;
            }
        }
    
        // Get the filtered products
        $products = $query->get();
    
        // Optionally get all the properties before applying search or filter, if needed
        $allProperties = [
            'categories' => Category::all(), // Assuming you have a Category model
            'brands' => Brand::all(),         // Assuming you have a Brand model
             // Custom logic to get available price ranges
            // Add more properties as needed
        ];
    
        // Return the filtered products and all available properties
        return response()->json([
            'products' => $products,
            'allProperties' => $allProperties,
        ]);
    }
    
    
    public function productBrand(Request $request)
    {
        $brand = Brand::all();

        return response()->json([
            'brand' => $brand,
        ]);
    }

    public function productCat(Request $request)
    {
        $products = Category::get();
       

        return response()->json([
            'category' => $products,
            
        ]);
    }
 
    public function createReview(Request $request)
{
    // Validate the request data
    $this->validate($request, [
        'rate' => 'required|numeric|min:1',
    ]);

    $product_info = Product::find($request->slug);
    
    if (!$product_info) {
        return response()->json(['message' => "product not found"], 404);
    }

    $data = $request->all();
    $data['product_id'] = $product_info->id;
    $data['user_id'] = $request->user()->id;
    $data['status'] = 'active';

    // Create the product review
    $status = ProductReview::create($data);

    // Notify admin (optional, can be omitted for pure API)
  
  
    if ($status) {
        return response()->json(['message' => 'Thank you for your feedback'], 200);
    } else {
        return response()->json(['message' => 'Something went wrong! Please try again!!'], 200);
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
    try {
        // Validate the request data
        $this->validate($request, [
            'first_name' => 'string|required',
            'last_name' => 'string|required',
            'address1' => 'string|required',
            'address2' => 'string|nullable',
            'coupon' => 'nullable|numeric',
            'phone' => 'numeric|required',
            'post_code' => 'string|nullable',
            'email' => 'string|required',
            'shipping' => 'required|exists:shippings,id', // Ensure shipping_id exists in shippings table
        ]);

        // Check if the cart is empty
        if (empty(Cart::where('user_id', auth()->user()->id)->where('order_id', null)->first())) {
            return response()->json(['message' => 'Cart is Empty!'], 400);
        }

        // Create a new order instance
        $order = new Order();
        $order_data = $request->all();
        $order_data['order_number'] = 'ORD-' . strtoupper(Str::random(10));
        $order_data['user_id'] = $request->user()->id;
        $order_data['shipping_id'] = $request->shipping;

        // Ensure shipping_id exists in the database
        $shipping = Shipping::find($order_data['shipping_id']);
        if (!$shipping) {
            return response()->json(['message' => 'Invalid shipping method selected.'], 400);
        }

        // Get the shipping price
        $order_data['sub_total'] = Helper::totalCartPrice();
        $order_data['quantity'] = Helper::cartCount();

        // Apply coupon if available
        if (session('coupon')) {
            $order_data['coupon'] = session('coupon')['value'];
        }

        $order_data['status'] = "new";

        // Set payment method and status
        if ($request->payment_method == 'paypal') {
            $order_data['payment_method'] = 'paypal';
            $order_data['payment_status'] = 'paid';
        } else {
            $order_data['payment_method'] = 'cod';
            $order_data['payment_status'] = 'Unpaid';
        }

        // Fill and save the order
        $order->fill($order_data);
        $status = $order->save();

        // If the order is saved successfully, update the cart
        if ($status) {
            Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => $order->id]);

            // Notify admin (optional, can be omitted in an API)
            $admins = AppUser::where('role', 'admin')->get(); // Ensure you fetch all admins
            $details = [
                'title' => 'New order created',
                'actionURL' => route('order.show', $order->id),
                'fas' => 'fa-file-alt'
            ];

            // Send the notification to each admin
            foreach ($admins as $admin) {
                $admin->notify(new StatusNotification($details));
            }

            // Return successful response
            return response()->json([
                'message' => 'Your product has been successfully placed in order',
                'order_id' => $order->id,
                'payment_method' => $order->payment_method
            ], 201);
        }

        return response()->json(['message' => 'Something went wrong! Please try again!'], 500);

    } catch (Exception $e) {
        // Catch any exception that occurs
        return response()->json([
            'error' => 'An error occurred while processing your order.',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
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
