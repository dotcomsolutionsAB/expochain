<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use Illuminate\Support\Str;

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
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        $register_user = User::create([
            'name' => $request->input('name'),
            'email' => strtolower($request->input('email')),
            'password' => bcrypt($request->input('password')),
            'mobile' => $request->input('mobile'),
            'company_id' => $request->input('company_id'),
        ]);
        
        unset($register_user['id'], $register_user['created_at'], $register_user['updated_at']);

        return isset($register_user) && $register_user !== null
        ? response()->json(['User registered successfully!', 'data' => $register_user], 201)
        : response()->json(['Failed to register user'], 400);
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
                'company' => $get_user_records->company ? $get_user_records->company->company_name : null, // Handle null case
            ];
        });
        

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
            'message' => "Import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
}
