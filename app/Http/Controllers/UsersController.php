<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Hash;

class UsersController extends Controller
{
    //
    //register user
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|unique:users,email',
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        $register_user = User::create([
            'name' => $request->input('name'),
            'email' => strtolower($request->input('email')),
            'password' => bcrypt($request->input('password')),
            'mobile' => $request->input('mobile'),
        ]);
        
        unset($register_user['id'], $register_user['created_at'], $register_user['updated_at']);

        return isset($register_user) && $register_user !== null
        ? response()->json(['User registered successfully!', 'data' => $register_user], 201)
        : response()->json(['Failed to register user'], 400);
    }

    //view
    public function view()
    {        
        $get_user_records = User::select('name','email', 'mobile', 'role')->get();
        

        return isset($get_user_records) && $get_user_records !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_user_records], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        $update_user = User::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'email' => strtolower($request->input('email')),
            'password' => bcrypt($request->input('password')),
            'mobile' => $request->input('mobile'),
        ]);
        
        return $update_user
        ? response()->json(['User record updated successfully!', 'data' => $update_user], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete($id)
    {
        // Delete the client
        $delete_user = User::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_user
        ? response()->json(['message' => 'Delete User record successfully!'], 204)
        : response()->json(['message' => 'Sorry, User record not found'], 400);
    }
}
