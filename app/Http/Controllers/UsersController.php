<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\User;
class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users=User::orderBy('id','ASC')->paginate(10);
        return view('backend.users.index')->with('users',$users);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.users.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'string|required|max:30',
            'email' => 'string|required|unique:users',
            'password' => 'string|required',
            'role' => 'required|in:admin,user',
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',  // Add validation for photo
        ]);
    
        // Initialize the data array from the request
        $data = $request->all();
        $data['password'] = Hash::make($request->password);
    
        // Check if a photo was uploaded
        if ($request->hasFile('photo')) {
            // Define the directory path
            $directoryPath = public_path('storage/users');
            
            // Create the directory if it doesn't exist
            if (!file_exists($directoryPath)) {
                mkdir($directoryPath, 0777, true); // Create folder with permissions
            }
    
            // Generate a unique file name for the photo
            $photoName = time() . '_' . $request->file('photo')->getClientOriginalName();
            
            // Move the uploaded photo to the directory
            $request->file('photo')->move($directoryPath, $photoName);
    
            // Save the photo path in the database
            $data['photo'] = 'storage/users/' . $photoName;  // Save relative path to the photo
        }
    
        // Create the user
        $status = User::create($data);
    
        // Flash a success or error message to the session
        if ($status) {
            request()->session()->flash('success', 'Successfully added user');
        } else {
            request()->session()->flash('error', 'Error occurred while adding user');
        }
    
        // Redirect back to the users index page
        return redirect()->route('users.index');
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
        $user=User::findOrFail($id);
        return view('backend.users.edit')->with('user',$user);
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
        // Find the user by id
        $user = User::findOrFail($id);
    
        // Validation
        $request->validate([
            'name' => 'string|required|max:30',
            'email' => 'string|required|email',
            'role' => 'required|in:admin,user',
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',  // Add validation for photo
        ]);
    
        // Initialize the data array from the request (all fields except photo)
        $data = $request->all();  // Now all fields are in the data array
    
        // If a new photo is uploaded
        if ($request->hasFile('photo')) {
            // Delete the old photo if it exists
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
    
            // Save the new photo path in the database
            $data['photo'] = 'storage/users/' . $photoName;
        }
    
        // Update the user data
        $status = $user->update($data);  // Use update() to update the fields in the database
    
        // Flash success or error message
        if ($status) {
            session()->flash('success', 'Successfully updated user');
        } else {
            session()->flash('error', 'Error occurred while updating user');
        }
    
        // Redirect back to the users index page
        return redirect()->route('users.index');
    }
    


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $delete=User::findorFail($id);
        $status=$delete->delete();
        if($status){
            request()->session()->flash('success','User Successfully deleted');
        }
        else{
            request()->session()->flash('error','There is an error while deleting users');
        }
        return redirect()->route('users.index');
    }
}
