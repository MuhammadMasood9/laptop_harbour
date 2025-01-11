<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products=Product::getAllProduct();
        // return $products;
        return view('backend.product.index')->with('products',$products);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $brand=Brand::get();
        $category=Category::where('is_parent',1)->get();
        // return $category;
        return view('backend.product.create')->with('categories',$category)->with('brands',$brand);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */


public function store(Request $request)
{
    // Validate the request data
    $this->validate($request, [
        'title' => 'string|required',
        'summary' => 'string|required',
        'description' => 'string|nullable',
        'photo' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048', // Make sure the photo is an image
        'size' => 'nullable',
        'stock' => "required|numeric",
        'cat_id' => 'required|exists:categories,id',
        'brand_id' => 'nullable|exists:brands,id',
        'child_cat_id' => 'nullable|exists:categories,id',
        'is_featured' => 'sometimes|in:1',
        'status' => 'required|in:active,inactive',
        'condition' => 'required|in:default,new,hot',
        'price' => 'required|numeric',
        'discount' => 'nullable|numeric'
    ]);
    

    // Initialize data
    $data = $request->all();
    
    // Generate a slug from the title
    $slug = Str::slug($request->title);
    
    // Check if the slug already exists in the database
    $count = Product::where('slug', $slug)->count();
    if ($count > 0) {
        $slug = $slug . '-' . date('ymdis') . '-' . rand(0, 999);
    }
    $data['slug'] = $slug;
    
    // Handle the 'is_featured' input, default to 0 if not provided
    $data['is_featured'] = $request->input('is_featured', 0);
    
    // Handle the 'size' input (if provided, convert to a comma-separated string)
    $size = $request->input('size');
    if ($size) {
        $data['size'] = implode(',', $size);
    } else {
        $data['size'] = '';
    }
    
    // Handle the image upload and store it in the public/storage/products folder
    if ($request->hasFile('photo')) {
        // Define the directory path
        $directoryPath = public_path('storage/products');
        
        // Create the directory if it doesn't exist
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0777, true); // Create folder with appropriate permissions
        }

        // Get the file from the request
        $file = $request->file('photo');

        // Generate a unique file name for the image
        $photoName = time() . '_' . $file->getClientOriginalName();
        
        // Move the uploaded image to the directory
        $file->move($directoryPath, $photoName);

        // Store the relative path of the image in the database
        $data['photo'] = 'storage/products/' . $photoName;
    }
    
    // Create the product
    $status = Product::create($data);

    // Flash a success or error message based on the result
    if ($status) {
        request()->session()->flash('success', 'Product successfully added');
    } else {
        request()->session()->flash('error', 'Please try again!');
    }

    // Redirect back to the product index page
    return redirect()->route('product.index');
}


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $brand=Brand::get();
        $product=Product::findOrFail($id);
        $category=Category::where('is_parent',1)->get();
        $items=Product::where('id',$id)->get();
        // return $items;
        return view('backend.product.edit')->with('product',$product)
                    ->with('brands',$brand)
                    ->with('categories',$category)->with('items',$items);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
    
        // Validate the request data
        $this->validate($request, [
            'title' => 'string|required',
            'summary' => 'string|required',
            'description' => 'string|nullable',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048', // Make photo field optional and validate it as an image
            'size' => 'nullable',
            'stock' => "required|numeric",
            'cat_id' => 'required|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'brand_id' => 'nullable|exists:brands,id',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric'
        ]);
    
        // Initialize the data array from the request
        $data = $request->all();
    
        // Handle 'is_featured' field
        $data['is_featured'] = $request->input('is_featured', 0);
    
        // Handle 'size' field (convert array to a comma-separated string)
        $size = $request->input('size');
        if ($size) {
            $data['size'] = implode(',', $size);
        } else {
            $data['size'] = '';
        }
    
        // Check if a new photo was uploaded
        if ($request->hasFile('photo')) {
            // Delete the old photo if it exists
            if ($product->photo && file_exists(public_path($product->photo))) {
                unlink(public_path($product->photo)); // Delete the old photo
            }
    
            // Define the directory path for the new image
            $directoryPath = public_path('storage/products');
            
            // Create the directory if it doesn't exist
            if (!file_exists($directoryPath)) {
                mkdir($directoryPath, 0777, true); // Create folder with appropriate permissions
            }
    
            // Get the uploaded file
            $file = $request->file('photo');
    
            // Generate a unique file name for the new image
            $photoName = time() . '_' . $file->getClientOriginalName();
    
            // Move the uploaded image to the directory
            $file->move($directoryPath, $photoName);
    
            // Save the new photo path in the database
            $data['photo'] = 'storage/products/' . $photoName;
        }
    
        // Update the product with the new data
        $status = $product->fill($data)->save();
    
        // Flash a success or error message based on the result
        if ($status) {
            request()->session()->flash('success', 'Product successfully updated');
        } else {
            request()->session()->flash('error', 'Please try again!');
        }
    
        // Redirect back to the product index page
        return redirect()->route('product.index');
    }
    

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product=Product::findOrFail($id);
        $status=$product->delete();
        
        if($status){
            request()->session()->flash('success','Product successfully deleted');
        }
        else{
            request()->session()->flash('error','Error while deleting product');
        }
        return redirect()->route('product.index');
    }
}
