<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\ClientsModel;
use App\Models\ClientsContactsModel;
use Illuminate\Support\Str;
use Auth;

class ClientsController extends Controller
{
    //
    // clients table
    // create
    public function add_clients(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:t_clients,name',
            'type' => 'required|string',
            'category' => 'required|string',
            'division' => 'required|string',
            'plant' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'gstin' => 'required|string|unique:t_clients,gstin',
            'contacts' => 'required',
        ]);

        $company_id = Auth::user()->company_id;

        // Check if the combination of name, gstin, and contact_id is unique
        $exists = ClientsModel::where('name', $request->input('name'))
                        ->where('gstin', $request->input('gstin'))
                        ->where('company_id', $company_id)
                        ->exists();

        if ($exists) {
            return response()->json(['error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
        }

        $customer_id = rand(1111111111,9999999999);

        $contacts = $request->input('contacts');

        // Iterate over the contacts array and insert each contact
        foreach ($contacts as $contact) {
            ClientsContactsModel::create([
            'customer_id' => $customer_id,
            'company_id' => $company_id,
            'name' => $contact['name'],
            'designation' => $contact['designation'],
            'mobile' => $contact['mobile'],
            'email' => $contact['email'],
            ]);
        }

        $register_clients = ClientsModel::create([
            'name' => $request->input('name'),
            'company_id' => Auth::user()->company_id,
            'customer_id' => $customer_id,
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'division' => $request->input('division'),
            'plant' => $request->input('plant'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
        ]);
        
        unset($register_clients['id'], $register_clients['created_at'], $register_clients['updated_at']);

        return isset($register_clients) && $register_clients !== null
        ? response()->json(['Client registered successfully!', 'data' => $register_clients], 201)
        : response()->json(['Failed to register client record'], 400);
    }

    // view
    public function view_clients()
    {        
        $get_clients = ClientsModel::with(['contacts' => function ($query)
        {
            $query->select('customer_id','name','designation','mobile','email');
        }])
        ->select('name','customer_id','type','category', 'division', 'plant', 'address_line_1', 'address_line_2', 'city','pincode','state','country', 'gstin')
        ->where('company_id',Auth::user()->company_id) 
        ->get();
        

        return isset($get_clients) && $get_clients !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_clients], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function update_clients(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'customer_id' => 'required',
            'type' => 'required|string',
            'category' => 'required|string',
            'division' => 'required|string',
            'plant' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'gstin' => 'required|string',
            'contacts' => 'required|array',
            'contacts.*.name' => 'required|string',
            'contacts.*.designation' => 'required|string',
            'contacts.*.mobile' => 'required|string',
            'contacts.*.email' => 'required|email',
        ]);

        // get the client
        $client = ClientsModel::where('customer_id', $request->input('customer_id'))->first();

        // Update the client information
        $clientUpdated = $client->update([
            'name' => $request->input('name'),
            // 'customer_id' => $customer_id,
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'division' => $request->input('division'),
            'plant' => $request->input('plant'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
        ]);

        // Get the list of contacts from the request
        $contacts = $request->input('contacts');

        // Collect names of contacts that are in the request
        $requestContactNames = [];

        $contactsUpdated = false;

        foreach ($contacts as $contactData) 
        {
            $requestContactNames[] = $contactData['name'];


            // Check if the contact exists by customer_id and name
            $contact = ClientsContactsModel::where('customer_id', $contactData['customer_id'])
                                            ->where('name',$contactData['name'])
                                            ->first();

            if ($contact) 
            {
                // Update the existing contact
                $contactsUpdated = $contact->update([
                    'designation' => $contactData['designation'],
                    'mobile' => $contactData['mobile'],
                    'email' => $contactData['email'],
                ]);
            }
            else
            {
                // Create a new contact since it doesn't exist
                $newContact = ClientsContactsModel::create([
                    'customer_id' => $client->customer_id,
                    'company_id' => Auth::user()->company_id,
                    'name' => $contactData['name'],
                    'designation' => $contactData['designation'],
                    'mobile' => $contactData['mobile'],
                    'email' => $contactData['email'],
                ]);

                if ($newContact) {
                    $contactsUpdated = true; // New contact created successfully
                }
            }
        }

        // Delete contacts that are not present in the request but exist in the database for this customer_id
        $contactsDeleted = ClientsContactsModel::where('customer_id', $client->customer_id)
        ->whereNotIn('name', $requestContactNames)  // Delete if name is not in the request
        ->delete();

        unset($client['id'], $client['created_at'], $client['updated_at']);

        return ($clientUpdated || $contactsUpdated || $contactsDeleted)
        ? response()->json(['message' => 'Client and contacts updated successfully!', 'client' => $client], 200)
        : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_clients($id)
    {
        // Try to find the client by the given ID
        $get_client_id = ClientsModel::select('customer_id', 'company_id')
                                     ->where('id', $id)
                                     ->first();
        
        // Check if the client exists

        if ($get_client_id && $get_client_id->company_id === Auth::user()->company_id) 
        {
            // Delete the client
            $delete_clients = ClientsModel::where('id', $id)->delete();

            // Delete associated contacts by customer_id
            $delete_contact_records = ClientsContactsModel::where('customer_id', $get_client_id->customer_id)->delete();

            // Return success response if deletion was successful
            return $delete_clients && $delete_contact_records
            ? response()->json(['message' => 'Client and associated contacts deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete client or contacts.'], 400);

        } 
        else 
        {
            // Return error response if client not found
            return response()->json(['message' => 'Client not found.'], 404);
        }
    }

    // migrate
    public function importClientsData()
    {
        ClientsModel::truncate();  
        
        ClientsContactsModel::truncate();  

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/clients.php'; // Replace with the actual URL

        // Fetch data from the external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        // Decode the JSON response
        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];

        foreach ($data as $record) {

            // Check if the client already exists by `name`, and skip if it does
            $existingClient = ClientsModel::where('name', $record['Name'])->first();
            if ($existingClient) {
                // Skip processing if client already exists
                continue;
            }

            // Assign default values if fields are missing
            $record['Type'] = !empty($record['Type']) ? $record['Type'] : 'Random Type_' . now()->timestamp . '_' . Str::random(5);
            $record['Category'] = !empty($record['Category']) ? $record['Category'] : 'Random Category_' . now()->timestamp . '_' . Str::random(5);
            $record['Division'] = !empty($record['Division']) ? $record['Division'] : 'Random Division_' . now()->timestamp . '_' . Str::random(5);
            $record['Plant'] = !empty($record['Plant']) ? $record['Plant'] : 'Random Plant_' . now()->timestamp . '_' . Str::random(5);
            $record['Address1'] = !empty($record['Address1']) ? $record['Address1'] : 'Random Address1' . now()->timestamp . '_' . Str::random(5); 
            $record['Address2'] = !empty($record['Address2']) ? $record['Address2'] : 'Random Address2' . now()->timestamp . '_' . Str::random(5); 
            $record['City'] = !empty($record['City']) ? $record['City'] : 'Random City' . now()->timestamp . '_' . Str::random(5); 
            $record['Pincode'] = !empty($record['Pincode']) ? $record['Pincode'] : 'Random Pincode' . now()->timestamp . '_' . Str::random(5); 
            $record['State'] = !empty($record['State']) ? $record['State'] : 'Random State' . now()->timestamp . '_' . Str::random(5);
            $record['GSTIN'] = !empty($record['GSTIN']) ? $record['GSTIN'] : 'Random GSTIN' . now()->timestamp . '_' . Str::random(5);
            $record['Country'] = !empty($record['Country']) ? $record['Country'] : 'India';

            // Generate placeholder email if the email is missing or invalid
            $record['Email'] = filter_var($record['Email'], FILTER_VALIDATE_EMAIL) ? $record['Email'] : 'placeholder_' . now()->timestamp . '@example.com';

            // Create validation data structure to match your request validation rules
            $validationData = [
                'name' => $record['Name'] ,
                'type' => $record['Type'] ,
                'category' => $record['Category'] ,
                'division' => $record['Division'] ,
                'plant' => $record['Plant'] ,
                'address_line_1' => $record['Address1'] ,
                'address_line_2' => $record['Address2'] ,
                'city' => $record['City'],
                'pincode' => $record['Pincode'] ,
                'state' => $record['State'] ,
                'country' => $record['Country'] ,
                'gstin' => $record['GSTIN'] ,
                'contacts' => [['name' => $record['Name'], 'mobile' => $record['Mobile'], 'email' => $record['Email']]],
            ];

            // Validate each record
            $validator = Validator::make($validationData, [
                'name' => 'required|string|unique:t_clients,name',
                'type' => 'required|string',
                'category' => 'required|string',
                'division' => 'required|string',
                'plant' => 'required|string',
                'address_line_1' => 'required|string',
                'address_line_2' => 'required|string',
                'city' => 'required|string',
                'pincode' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'gstin' => 'required|string|unique:t_clients,gstin',
                'contacts' => 'required|array',
                'contacts.*.name' => 'required|string',
                'contacts.*.mobile' => 'nullable|string',
                'contacts.*.email' => 'nullable|email',
            ]);

            // dd($validator);

            if ($validator->fails()) {
                // If validation fails due to duplicate GSTIN, modify it
                if ($validator->errors()->has('gstin')) {
                    $record['GSTIN'] = 'Dup GSTIN_' . now()->timestamp . '_' . Str::random(5);
                    $validationData['gstin'] = $record['GSTIN'];
                    $validator = Validator::make($validationData, [
                        'gstin' => 'required|string|unique:t_clients,gstin',
                    ]);
                }
        
                // Recheck validation with updated GSTIN
                if ($validator->fails()) {
                    $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                    continue;
                }
            }

            // Generate unique customer ID
            $customer_id = rand(1111111111, 9999999999);

            // Save contacts to `ClientsContactsModel`
            foreach ($validationData['contacts'] as $contact) {
                ClientsContactsModel::create([
                    'customer_id' => $customer_id,
                    'name' => $contact['name'],
                    // 'designation' => $contact['designation'],
                    'designation' => 'Random Designation'. now()->timestamp,
                    'mobile' => $contact['mobile'],
                    'email' => $contact['email'] ,
                ]);
            }

            // Save client to `ClientsModel`
            try {
                ClientsModel::create([
                    'name' => $validationData['name'],
                    'customer_id' => $customer_id,
                    'type' => $validationData['type'],
                    'category' => $validationData['category'],
                    'division' => $validationData['division'],
                    'plant' => $validationData['plant'],
                    'address_line_1' => $validationData['address_line_1'],
                    'address_line_2' => $validationData['address_line_2'],
                    'city' => $validationData['city'],
                    'pincode' => $validationData['pincode'],
                    'state' => $validationData['state'],
                    'country' => $validationData['country'],
                    'gstin' => $validationData['gstin'],
                ]);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert record: ' . $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
}
