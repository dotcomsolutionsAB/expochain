<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\ClientsModel;
use App\Models\ClientContactsModel;
use App\Models\ClientAddressModel;
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
            'gstin' => 'required|string|unique:t_clients,gstin',
            'contacts' => 'required|array|min:1',
            'addresses' => 'required|array|min:1', // Array for addresses
            'addresses.*.type' => 'required|in:billing,shipping',
            'addresses.*.address_line_1' => 'required|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.state' => 'required|string',
            'addresses.*.pincode' => 'required|string',
            'addresses.*.country' => 'required|string',
        ]);

        $company_id = Auth::user()->company_id;

        // Check if the combination of name, gstin, and company_id is unique
        $exists = ClientsModel::where('name', $request->input('name'))
            ->where('gstin', $request->input('gstin'))
            ->where('company_id', $company_id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
        }

        $customer_id = rand(1111111111, 9999999999);

        // Save contacts
        $contacts = $request->input('contacts');
        $defaultContactId = null;

        foreach ($contacts as $index => $contact) {
            $newContact = ClientContactsModel::create([
                'customer_id' => $customer_id,
                'company_id' => $company_id,
                'name' => $contact['name'],
                'designation' => $contact['designation'],
                'mobile' => $contact['mobile'],
                'email' => $contact['email'],
            ]);

            // Set the first contact as the default if no default is specified
            if ($index === 0) {
                $defaultContactId = $newContact->id;
            }
        }

        // Save client details
        $register_clients = ClientsModel::create([
            'name' => $request->input('name'),
            'company_id' => $company_id,
            'customer_id' => $customer_id,
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'division' => $request->input('division'),
            'plant' => $request->input('plant'),
            'gstin' => $request->input('gstin'),
            'default_contact' => $defaultContactId,
        ]);

        // Save addresses
        foreach ($request->input('addresses') as $address) {
            ClientAddressModel::create([
                'company_id' => $company_id,
                'type' => $address['type'], // Billing or Shipping
                'customer_id' => $customer_id, // Mapping client ID
                'country' => $address['country'],
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'],
                'city' => $address['city'],
                'state' => $address['state'],
                'pincode' => $address['pincode'],
            ]);
        }

        unset($register_clients['id'], $register_clients['created_at'], $register_clients['updated_at']);

        return isset($register_clients) && $register_clients !== null
            ? response()->json(['code' => 201, 'success' => true, 'Client registered successfully!', 'data' => $register_clients], 201)
            : response()->json(['code' => 400, 'success' => false, 'message' => 'Failed to register client record'], 400);
    }

    // view
    public function view_clients(Request $request, $id = null)
    {
        if ($id) {
            // Fetch a specific client
            $client = ClientsModel::with([
                'contacts' => function ($query) {
                    $query->select('customer_id', 'name', 'designation', 'mobile', 'email');
                },
                'addresses' => function ($query) {
                    $query->select('customer_id', 'type', 'country', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode');
                },
            ])
            ->select('customer_id', 'type', 'category', 'division', 'plant', 'gstin', 'company_id')
            ->where('company_id', Auth::user()->company_id)
            ->where('customer_id', $id)
            ->first();

            if ($client) {
                $contactCount = $client->contacts->count();

            // Trim unnecessary fields from contacts
            $client->contacts->each(function ($contact) {
                $contact->makeHidden(['id', 'created_at', 'updated_at']);
            });

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Client fetched successfully',
                    'data' => $client,
                    'contact_count' => $contactCount,
                ], 200);
            }

            return response()->json(['code' => 404, 'success' => false, 'message' => 'Client not found'], 404);
        } else {
            // Fetch all clients with optional filters
            $name = $request->input('name');
            $type = $request->input('type');
            $category = $request->input('category');
            $division = $request->input('division');
            $gstin = $request->input('gstin');
            $mobile = $request->input('mobile');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $clients = ClientsModel::with([
                'contacts' => function ($query) use ($mobile) {
                    if ($mobile) {
                        $query->where('mobile', 'LIKE', '%' . $mobile . '%');
                    }
                },
                'addresses' => function ($query) {
                    $query->select('customer_id', 'type', 'country', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode');
                },
            ])
            ->select('name', 'customer_id', 'type', 'category', 'division', 'plant', 'gstin', 'company_id')
            ->where('company_id', Auth::user()->company_id)
            ->when($name, function ($query, $name) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            })
            ->when($type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($category, function ($query, $category) {
                $query->where('category', $category);
            })
            ->when($division, function ($query, $division) {
                $query->where('division', $division);
            })
            ->when($gstin, function ($query, $gstin) {
                $query->where('gstin', 'LIKE', '%' . $gstin . '%');
            })
            ->offset($offset)
            ->limit($limit)
            ->get();

            $clients->each(function ($client) {
                $client->contact_count = $client->contacts->count(); // Add contact count

                // Trim unnecessary fields from contacts
                $client->contacts->each(function ($contact) {
                    $contact->makeHidden(['id', 'created_at', 'updated_at']);
                });
            });

            return $clients->isNotEmpty()
                ? response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Clients fetched successfully',
                    'data' => $clients,
                    'total_contacts' => $clients->sum(fn($client) => $client->contacts->count()), // Sum all contacts
                    'count' => $clients->count(), // Total clients count
                ], 200)
                : response()->json(['code' => 404, 'success' => false, 'message' => 'No clients available'], 404);
        }
    }

    // update
    public function update_clients(Request $request, $id)
    {
        // Validate the request input
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'category' => 'required|string',
            'division' => 'required|string',
            'plant' => 'required|string',
            'gstin' => 'required|string',
            'contacts' => 'required|array',
            'contacts.*.name' => 'required|string',
            'contacts.*.designation' => 'required|string',
            'contacts.*.mobile' => 'required|string',
            'contacts.*.email' => 'required|email',
            'addresses' => 'required|array',
            'addresses.*.type' => 'required|in:billing,shipping',
            'addresses.*.address_line_1' => 'required|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.state' => 'required|string',
            'addresses.*.pincode' => 'required|string',
            'addresses.*.country' => 'required|string',
        ]);

        // Validate and fetch the client by ID
        $client = ClientsModel::where('id', $id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$client) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Client not found.'], 404);
        }

        // Update client details
        $clientUpdated = $client->update([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'division' => $request->input('division'),
            'plant' => $request->input('plant'),
            'gstin' => $request->input('gstin'),
        ]);

        // Update contacts
        $contacts = $request->input('contacts');
        $contactNames = [];
        $contactsUpdated = false;

        foreach ($contacts as $contactData) {
            $contactNames[] = $contactData['name'];

            $contact = ClientContactsModel::where('customer_id', $client->customer_id)
                ->where('name', $contactData['name'])
                ->first();

            if ($contact) {
                // Update existing contact
                $contactsUpdated = $contact->update([
                    'designation' => $contactData['designation'],
                    'mobile' => $contactData['mobile'],
                    'email' => $contactData['email'],
                ]);
            } else {
                // Create a new contact
                $newContact = ClientContactsModel::create([
                    'customer_id' => $client->customer_id,
                    'company_id' => Auth::user()->company_id,
                    'name' => $contactData['name'],
                    'designation' => $contactData['designation'],
                    'mobile' => $contactData['mobile'],
                    'email' => $contactData['email'],
                ]);

                if ($newContact) {
                    $contactsUpdated = true;
                }
            }
        }

        // Remove contacts not in the request
        $contactsDeleted = ClientContactsModel::where('customer_id', $client->id)
            ->whereNotIn('name', $contactNames)
            ->delete();

        // Update addresses
        $addresses = $request->input('addresses');
        $addressTypes = [];
        $addressesUpdated = false;

        foreach ($addresses as $addressData) {
            $addressTypes[] = $addressData['type'];

            $address = ClientAddressModel::where('customer_id', $client->customer_id)
                ->where('type', $addressData['type'])
                ->first();

            if ($address) {
                // Update existing address
                $addressesUpdated = $address->update([
                    'address_line_1' => $addressData['address_line_1'],
                    'address_line_2' => $addressData['address_line_2'],
                    'city' => $addressData['city'],
                    'state' => $addressData['state'],
                    'pincode' => $addressData['pincode'],
                    'country' => $addressData['country'],
                ]);
            } else {
                // Create a new address
                $newAddress = ClientAddressModel::create([
                    'company_id' => Auth::user()->company_id,
                    'type' => $addressData['type'],
                    'customer_id' => $client->customer_id,
                    'address_line_1' => $addressData['address_line_1'],
                    'address_line_2' => $addressData['address_line_2'],
                    'city' => $addressData['city'],
                    'state' => $addressData['state'],
                    'pincode' => $addressData['pincode'],
                    'country' => $addressData['country'],
                ]);

                if ($newAddress) {
                    $addressesUpdated = true;
                }
            }
        }

        // Remove addresses not in the request
        $addressesDeleted = ClientAddressModel::where('customer_id', $client->customer_id)
            ->whereNotIn('type', $addressTypes)
            ->delete();

        return ($clientUpdated || $contactsUpdated || $contactsDeleted || $addressesUpdated || $addressesDeleted)
            ? response()->json(['code' => 200, 'success' => true, 'message' => 'Client, contacts, and addresses updated successfully!', 'client' => $client], 200)
            : response()->json(['code' => 304, 'success' => false, 'message' => 'No changes detected.'], 304);
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
            $delete_contact_records = ClientContactsModel::where('customer_id', $get_client_id->customer_id)->delete();

            // Delete associated address by customer_id
            $delete_address_records = ClientAddressModel::where('customer_id', $get_client_id->customer_id)->delete();

            // Return success response if deletion was successful
            return $delete_clients && $delete_contact_records && $delete_address_records
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Client and associated contacts and addresses deleted successfully!'], 200)
            : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete client or contacts.'], 400);

        } 
        else 
        {
            // Return error response if client not found
            return response()->json(['code' => 404,'success' => false, 'message' => 'Client not found.'], 404);
        }
    }

    // migrate
    public function importClientsData()
    {
        ClientsModel::truncate();  
        
        ClientContactsModel::truncate();  

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

            // Save contacts to `ClientContactsModel`
            foreach ($validationData['contacts'] as $contact) {
                ClientContactsModel::create([
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
            'code' => 200,
            'success' => true,
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
}
