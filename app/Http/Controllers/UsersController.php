<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use Illuminate\Support\Str;
use Auth;

class UsersController extends Controller
{
    //
    //register user
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'company_id' => 'required|integer',
            'email' => [
                'required',
                'email',
                function ($attribute, $value, $fail) use ($request) {
                    // Check if the combination of email and contact_id already exists
                    $exists = \App\Models\User::where('email', $value)
                                            ->where('company_id', $request->input('company_id'))
                                            ->exists();
                    if ($exists) {
                        $fail('The combination of email and company ID must be unique.');
                    }
                },
            ],
            // 'mobile' => 'required|string',
            'mobile' => [
                'required',
                'string',
                'regex:/^\d{13,20}$/',
            ],
            'password' => 'required|string',
            'username' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    $exists = \App\Models\User::where('username', $value)
                        ->where('company_id', $request->input('company_id'))
                        ->exists();
                    if ($exists) {
                        $fail('The combination of username and company ID must be unique.');
                    }
                },
            ], // Allow username to be nullable
        ]);

         // If username is null, set email as the username
        $username = $request->input('username') ?? strtolower($request->input('email'));

        $register_user = User::create([
            'name' => $request->input('name'),
            'email' => strtolower($request->input('email')),
            'password' => bcrypt($request->input('password')),
            'mobile' => $request->input('mobile'),
            'company_id' => $request->input('company_id'),
            'username' => $username,
        ]);
        
        unset($register_user['id'], $register_user['created_at'], $register_user['updated_at']);

        return isset($register_user) && $register_user !== null
        ? response()->json(['code' => 201,'success' => true, 'User registered successfully!', 'data' => $register_user], 201)
        : response()->json(['code' => 400,'success' => false, 'Failed to register user'], 400);
    }

    //view
    public function view()
    {        
        $get_user_records = User::with(['company' => function ($query)
        {
            $query->select('id', 'company_name');
        }])
        ->select('name','email', 'mobile', 'role', 'company_id')
        ->get()
        ->map(function ($get_user_records) {
            return [
                'name' => $get_user_records->name,
                'email' => $get_user_records->email,
                'mobile' => $get_user_records->mobile,
                'role' => $get_user_records->role,
                // 'company' => $get_user_records->company ? $get_user_records->company->company_name : null, // Handle null case
                'company' => $get_user_records->company_id,
            ];
        });
        

        return isset($get_user_records) && $get_user_records !== null
        ? response()->json(['code' => 200,'success' => true, 'Fetch data successfully!', 'data' => $get_user_records], 200)
        : response()->json(['code' => 404,'success' => false, 'Failed to fetch data'], 404); 
    }

    // view user's record
    // public function view_user()
    // {     
    //     $get_records = User::select('name','email', 'mobile', 'role')
    //                         ->where('id', Auth::id())
    //                         ->get();

    //     return isset($get_records) && $get_records !== null
    //     ? response()->json(['Fetch data successfully!', 'data' => $get_records], 200)
    //     : response()->json(['Failed to fetch data'], 404); 
    // }
    public function view_user(Request $request)
    {
        // Get filter inputs
        $name = $request->input('name'); // Filter by name
        $email = $request->input('email'); // Filter by email
        $mobile = $request->input('mobile'); // Filter by mobile
        $role = $request->input('role'); // Filter by role
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = User::select('name', 'email', 'mobile', 'role')
            ->where('id', Auth::id()); // Ensure the user is authorized

        // Apply filters
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($email) {
            $query->where('email', 'LIKE', '%' . $email . '%');
        }
        if ($mobile) {
            $query->where('mobile', 'LIKE', '%' . $mobile . '%');
        }
        if ($role) {
            $query->where('role', $role);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Execute the query
        $get_records = $query->get();

        // Return the response
        return $get_records->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $get_records,
                'count' => $get_records->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No records found!',
            ], 404);
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
        ? response()->json(['code' => 200,'success' => true, 'User record updated successfully!', 'data' => $update_user], 200)
        : response()->json(['code' => 204,'success' => false, 'No changes detected'], 204);
    }

    // delete
    public function delete($id)
    {
        // Delete the client
        $delete_user = User::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_user
        ? response()->json(['code' => 204,'success' => true, 'message' => 'Delete User record successfully!'], 204)
        : response()->json(['code' => 400,'success' => false, 'message' => 'Sorry, User record not found'], 400);
    }

    // migrate from old
    public function get_migrate()
    {
        // Truncate the table to remove existing data
        User::truncate();  // Clears all existing records in the 'users' table

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/users.php'; // replace with the actual URL
        
        // Fetch data from the external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        // Decode the response
        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        // Process and save each record
        $successfulInserts = 0;
        $errors = [];
        
        foreach ($data as $record) {

             // Generate a random email if email is null or empty
            if (empty($record['email'])) {
                $record['email'] = 'user_' . uniqid() . '@randomdomain.com';
            }
            
            // Generate a random mobile if mobile is null or empty
            if (empty($record['mobile'])) {
                $record['mobile'] = '+911234567890'; // Example random number or dynamically generate if needed
            }

            // Validate each record
            $validator = Validator::make($record, [
                'name' => 'required|string',
                'email' => 'nullable|email|unique:users,email',
                'mobile' => 'nullable|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

             // Insert the record into the database
            try {
                User::create([
                    'name' => $record['name'],
                    'email' => strtolower($record['email']),
                    'password' => ($record['password']),
                    'mobile' => $record['mobile'],
                ]);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert record: ' . $e->getMessage()];
            }
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
}
