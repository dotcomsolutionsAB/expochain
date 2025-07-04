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
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientsController extends Controller
{
    //
    // clients table
    // create
    public function add_clients(Request $request)
    {
        $request->validate([
            // 'name' => 'required|string|unique:t_clients,name',
            // 'email' => 'email',
            // 'type' => 'required|string',
            // 'category' => 'required|string',
            // 'division' => 'string',
            // 'plant' => 'nullable|string',
            // 'gstin' => 'nullable|string|unique:t_clients,gstin'
            'name' => 'required|string|unique:t_clients,name',
            'mobile' => 'nullable|string',
            'email' => 'nullable|email',
            'type' => 'required|string',
            'category' => 'required|string',
            'division' => 'nullable|string',
            'plant' => 'nullable|string',
            'gstin' => 'nullable|string|unique:t_clients,gstin',

            'contacts' => 'nullable|array|min:1', // ✅ Contacts must be an array with at least 1 contact
            'contacts.*.name' => 'required_with:contacts|string',
            'contacts.*.designation' => 'nullable|string',
            'contacts.*.mobile' => 'required_with:contacts|string|min:10|max:15|unique:t_client_contacts,mobile',
            'contacts.*.email' => 'nullable|email',

            'addresses' => 'nullable|array|min:1', // ✅ Addresses must be an array with at least 1 address
            'addresses.*.type' => 'required_with:addresses|string|in:billing,shipping', // ✅ Must be "Billing" or "Shipping"
            'addresses.*.country' => 'required_with:addresses|string',
            'addresses.*.z' => 'required_with:addresses|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'required_with:addresses|string',
            'addresses.*.state' => 'required_with:addresses|string',
            'addresses.*.pincode' => 'required_with:addresses|string|min:4|max:10',
        ]);

        $company_id = Auth::user()->company_id;

        // Check if the combination of name, gstin, and company_id is unique
        $exists = ClientsModel::where('name', $request->input('name'))
            ->where('gstin', $request->input('gstin'))
            ->where('company_id', $company_id)
            ->exists();

        if ($exists) {
            return response()->json(['code' => 422, 'success' => false, 'error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
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
            'mobile' => $request->input('mobile'),
            'email' => $request->input('email'),
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
            ? response()->json(['code' => 201, 'success' => true, 'message' => 'Client registered successfully!', 'data' => $register_clients], 201)
            : response()->json(['code' => 400, 'success' => false, 'message' => 'Failed to register client record'], 400);
    }

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
            ->select('id','name','customer_id', 'type', 'category', 'division', 'plant', 'gstin', 'company_id')
            ->where('company_id', Auth::user()->company_id)
            ->where('id', $id)
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
            $search = $request->input('search');
            $type = $request->input('type') ? explode(',', $request->input('type')) : null;
            $category = $request->input('category') ? explode(',', $request->input('category')) : null;
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            // Get total count of records in `t_products`
            $total_clients = ClientsModel::count(); 

            $clientsQuery = ClientsModel::with([
                'contacts' => function ($query) use ($search) {
                    if ($search) {
                        $query->where('mobile', 'LIKE', '%' . $search . '%');
                    }
                },
                'addresses' => function ($query) {
                    $query->select('customer_id', 'type', 'country', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode');
                },
            ])
            ->select('id', 'name', 'customer_id', 'type', 'category', 'division', 'plant', 'gstin', 'company_id')
            ->where('company_id', Auth::user()->company_id);

            // Apply search filter
            if ($search) {
                $clientsQuery->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('gstin', 'LIKE', '%' . $search . '%')
                        ->orWhereHas('contacts', function ($q) use ($search) {
                            $q->where('mobile', 'LIKE', '%' . $search . '%');
                        });
                });
            }

            // Apply type filter
            if ($type) {
                $clientsQuery->whereIn('type', $type);
            }

            // Apply category filter
            if ($category) {
                $clientsQuery->whereIn('category', $category);
            }

            $clients = $clientsQuery->offset($offset)->limit($limit)->get();

            $clients->each(function ($client) {
                $client->contact_count = $client->contacts->count();

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
                'count' => $clients->count(), // Total clients count
                'total_records' => $total_clients,
            ], 200)
            : response()->json(['code' => 200, 'success' => true, 'message' => 'No clients available', 'data' => [], 'count' => 0, 'total_records' => 0], 200);

        }
    }

    // update
    public function update_clients(Request $request, $id)
    {
        // Validate the request input
        $request->validate([
            'name' => 'required|string',
            'mobile' => 'required|string|size:13',
            'email' => 'required|email',
            'mobile' => $request->input('mobile'),
            'email' => $request->input('email'),
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

    // update address
    public function update_client_address(Request $request, $client_id)
    {
        // Validate the request input
        $request->validate([
            'type' => 'required|in:billing,shipping',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'pincode' => 'required|string',
            'country' => 'required|string',
        ]);

        // Fetch the client by ID and company_id
        $client = ClientsModel::where('id', $client_id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$client) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Client not found!',
            ], 404);
        }

        // Check if an address of this type already exists
        $existingAddress = ClientAddressModel::where('customer_id', $client->customer_id)
            ->where('type', $request->input('type'))
            ->first();

        if ($existingAddress) {
            // Update the existing address
            $existingAddress->update([
                'address_line_1' => $request->input('address_line_1'),
                'address_line_2' => $request->input('address_line_2'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'pincode' => $request->input('pincode'),
                'country' => $request->input('country'),
            ]);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Address updated successfully!',
                'data' => $existingAddress->makeHidden(['id', 'created_at', 'updated_at']),
            ], 200);
        } else {
            // Create a new address entry
            $newAddress = ClientAddressModel::create([
                'customer_id' => $client->customer_id,
                'company_id' => Auth::user()->company_id,
                'type' => $request->input('type'),
                'address_line_1' => $request->input('address_line_1'),
                'address_line_2' => $request->input('address_line_2'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'pincode' => $request->input('pincode'),
                'country' => $request->input('country'),
            ]);

            return response()->json([
                'code' => 201,
                'success' => true,
                'message' => 'Address added successfully!',
                'data' => $newAddress->makeHidden(['id', 'created_at', 'updated_at']),
            ], 201);
        }
    }

    // update gst
    public function update_client_gst(Request $request, $client_id)
    {
        // Validate the request input
        $request->validate([
            'gst' => 'required|string',
        ]);

        // Fetch the client by ID and company_id
        $client = ClientsModel::where('id', $client_id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$client) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Client not found!',
            ], 404);
        }

            // ✅ **Update GST if the client exists**
            $client->update([
                'gstin' => $request->input('gst')
            ]);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client GST updated successfully!',
                'data' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'gst' => $client->gstin
                ]
            ], 200);
    }

    // migrate
    public function importClientsData()
    {
        ClientsModel::truncate();
        ClientContactsModel::truncate();
        ClientAddressModel::truncate();
    
        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/clients.php';

           // Increase memory limit and max execution time
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300); // Set to 5 minutes
    
        // Fetch data from the external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }
    
        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }
    
        // Decode the JSON response
        $data = $response->json('data');
    
        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }
    
        $successfulInserts = 0;
        $errors = [];
        $recordsStatus = [];  // <-- NEW: track each record status
        $company_id = Auth::user()->company_id;

        // Prepare batch arrays
        $clientsBatch = [];
        $contactsBatch = [];
        $addressesBatch = [];
        $batchSize = 100; // Set batch size
        $skippedRecords = [];
        $allSkipped = [];

        foreach ($data as $record) {
            $processedRecord = [
                'name' => $record['Name'] ?? '(no name)',
                'status' => 'pending',
                'reason' => '',
                'record' => $record,
            ];
            // Check if the client already exists by `name`, and skip if it does
            // $existingClient = ClientsModel::where('name', $record['Name'])->first();
            // if ($existingClient) {
            //     continue; // Skip processing if client already exists
            // }
            // Check if Name is present and not empty
            if (!isset($record['Name']) || trim($record['Name']) === '') {
                $processedRecord['status'] = 'skipped';
                $processedRecord['reason'] = 'Missing or empty Name field';
                $skippedRecords[] = $processedRecord;
                $recordsStatus[] = $processedRecord;
                continue;
            }

            $clientNameTrimmed = trim($record['Name']);
            // Case-insensitive, trimmed duplicate check
            $existingClient = ClientsModel::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($clientNameTrimmed)])->first();

            if ($existingClient) {
                $processedRecord['status'] = 'skipped';
                $processedRecord['reason'] = 'Client name already exists';
                $skippedRecords[] = $processedRecord;
                $recordsStatus[] = $processedRecord;
                continue;
            }

    
            // Assign default values if fields are missing
            $record['Type'] = $record['Type'] ?? null;
            $record['Category'] = $record['Category'] ?? null;
            $record['Division'] = $record['Division'] ?? null;
            $record['Plant'] = $record['Plant'] ?? null;
            $record['Address1'] = $record['Address1'] ?? null;
            $record['Address2'] = $record['Address2'] ?? null;
            $record['City'] = $record['City'] ?? null;
            $record['Pincode'] = $record['Pincode'] ?? null;
            $record['State'] = $record['State'] ?? null;
            $record['GSTIN'] = $record['GSTIN'] ?? null;
            $record['Country'] = $record['Country'] ?? null;

            // Sanitize GSTIN
            $record['GSTIN'] = trim($record['GSTIN']);
            if ($record['GSTIN'] === '' || strlen($record['GSTIN']) > 15 || !preg_match('/^[0-9A-Z]{1,15}$/', $record['GSTIN'])) {
                $record['GSTIN'] = null;
            }

            // Make unique if GSTIN exists
            if ($record['GSTIN']) {
                while (ClientsModel::where('gstin', $record['GSTIN'])->exists()) {
                    $record['GSTIN'] = '-' . $record['GSTIN'];
                }
            }
            // process GSTIN
            // while (ClientsModel::where('gstin', $record['GSTIN'])->exists()) {
            //     $record['GSTIN'] = '-' . $record['GSTIN']; // Prepend `-` if GSTIN already exists
            // }
    
            // Process mobile and email
            $mobileList = array_filter(explode(',', $record['Mobile'] ?? '')); // Filter out empty values
            $primaryMobile = $mobileList[0] ?? null; // Use the first mobile number, or default
    
            $primaryEmail = filter_var(trim($record['Email'] ?? ''), FILTER_VALIDATE_EMAIL)
                ? trim($record['Email'])
                : null;
    
            // Prepare validation data
            $validationData = [
                'name' => $record['Name'],
                'type' => $record['Type'],
                'category' => $record['Category'],
                'division' => $record['Division'],
                'plant' => $record['Plant'],
                'address_line_1' => $record['Address1'],
                'address_line_2' => $record['Address2'],
                'city' => $record['City'],
                'pincode' => $record['Pincode'],
                'state' => $record['State'],
                'country' => $record['Country'],
                'gstin' => $record['GSTIN'],
                'mobile' => $primaryMobile,
                'email' => $primaryEmail,
                'contacts' => [['name' => $record['Name'], 'mobile' => $primaryMobile, 'email' => $primaryEmail]],
            ];
    
            // Validate data
            $validator = Validator::make($validationData, [
                'name' => 'required|string|unique:t_clients,name',
                'type' => 'nullable|string',
                'category' => 'nullable|string',
                'division' => 'nullable|string',
                'plant' => 'nullable|string',
                'address_line_1' => 'nullable|string',
                'address_line_2' => 'nullable|string',
                'city' => 'nullable|string',
                'pincode' => 'nullable|string',
                'state' => 'nullable|string',
                'country' => 'nullable|string',
                'gstin' => 'nullable|string|unique:t_clients,gstin',
                'mobile' => 'nullable|string',
                'email' => 'nullable|email',
                'contacts' => 'required|array',
            ]);
    
            // if ($validator->fails()) {
            //     $errors[] = ['record' => $record, 'errors' => $validator->errors()];
            //     continue;
            // }
            if ($validator->fails()) {
                $processedRecord['status'] = 'error';
                $processedRecord['reason'] = 'Validation failed';
                $processedRecord['validation_errors'] = $validator->errors()->all();
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                $recordsStatus[] = $processedRecord;
                continue;
            }

    
            // Generate unique customer ID
            $customer_id = rand(1111111111, 9999999999);
    
            // Save contacts to `ClientContactsModel`
            foreach ($validationData['contacts'] as $contact) {
                ClientContactsModel::create([
                    'customer_id' => $customer_id,
                    'company_id' => $company_id,
                    'name' => $contact['name'],
                    'designation' => 'Default Designation',
                    'mobile' => $contact['mobile'],
                    'email' => $contact['email'],
                ]);
            }
    
            // Save client address to `ClientAddressModel` only if address fields exist
            if (!empty($validationData['address_line_1']) || !empty($validationData['city']) || !empty($validationData['pincode'])) {
                ClientAddressModel::create([
                    'customer_id' => $customer_id,
                    'company_id' => $company_id,
                    'address_line_1' => $validationData['address_line_1'],
                    'address_line_2' => $validationData['address_line_2'],
                    'city' => $validationData['city'],
                    'pincode' => $validationData['pincode'],
                    'state' => $validationData['state'],
                    'country' => $validationData['country'],
                ]);
            }
    
            // Save client to `ClientsModel`
            try {
                ClientsModel::create([
                    'name' => $validationData['name'],
                    'customer_id' => $customer_id,
                    'company_id' => $company_id,
                    'type' => $validationData['type'],
                    'category' => $validationData['category'],
                    'division' => $validationData['division'],
                    'plant' => $validationData['plant'],
                    'gstin' => $validationData['gstin'],
                    'mobile' => $validationData['mobile'], // Store mobile in ClientsModel
                    'email' => $validationData['email'], // Store email in ClientsModel
                ]);
                $successfulInserts++;
                $processedRecord['status'] = 'inserted';
                $recordsStatus[] = $processedRecord;
            } catch (\Exception $e) {
                $processedRecord['status'] = 'error';
                $processedRecord['reason'] = 'Insert failed';
                $processedRecord['exception_message'] = $e->getMessage();
                $errors[] = ['record' => $record, 'error' => 'Failed to insert record: ' . $e->getMessage()];
                $recordsStatus[] = $processedRecord;
            }

            // Count records status summary
            $counts = [
                'inserted' => 0,
                'skipped' => 0,
                'error' => 0,
                'pending' => 0,
            ];

            foreach ($recordsStatus as $rec) {
                if (isset($counts[$rec['status']])) {
                    $counts[$rec['status']]++;
                } else {
                    $counts['pending']++;
                }
            }
        }
    
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
            'skipped' => $skippedRecords,
            'records_status' => $recordsStatus,
            'counts' => $counts,
        ], 200);
    }
    
    public function export_clients(Request $request)
    {
        // Check for comma-separated IDs
        $ids = $request->input('id') ? explode(',', $request->input('id')) : null;
        $search = $request->input('search'); // Optional search input
        $type = $request->input('type') ? explode(',', $request->input('type')) : null;
        $category = $request->input('category') ? explode(',', $request->input('category')) : null;

        $clientsQuery = ClientsModel::query()
            ->select('customer_id', 'name', 'type', 'category', 'division', 'plant', 'gstin', 'company_id')
            ->where('company_id', Auth::user()->company_id);

        // If IDs are provided, prioritize them
        if ($ids) {
            $clientsQuery->whereIn('id', $ids);
        } else {
            // Apply search filter if IDs are not provided
            if ($search) {
                $clientsQuery->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('gstin', 'LIKE', '%' . $search . '%');
                });
            }

            // Apply type filter
            if ($type) {
                $clientsQuery->whereIn('type', $type);
            }

            // Apply category filter
            if ($category) {
                $clientsQuery->whereIn('category', $category);
            }
        }

        $clients = $clientsQuery->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No clients found to export!',
            ], 404);
        }

        // Format data for Excel
        $exportData = $clients->map(function ($client) {
            return [
                'Client ID' => $client->customer_id,
                'Name' => $client->name,
                'Type' => $client->type,
                'Category' => $client->category,
                'Division' => $client->division,
                'Plant' => $client->plant,
                'GSTIN' => $client->gstin,
            ];
        })->toArray();

        // Generate the file path
        $fileName = 'clients_export_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'uploads/clients_excel/' . $fileName;

        // Save Excel to storage
        Excel::store(new class($exportData) implements FromCollection, WithHeadings {
            private $data;

            public function __construct(array $data)
            {
                $this->data = collect($data);
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Client ID',
                    'Name',
                    'Type',
                    'Category',
                    'Division',
                    'Plant',
                    'GSTIN',
                ];
            }
        }, $filePath, 'public');

        // Get file details
        $fileUrl = asset('storage/' . $filePath);
        $fileSize = Storage::disk('public')->size($filePath);

        // Return response with file details
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'File available for download',
            'data' => [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                // 'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'content_type' => 'Excel',
            ],
        ], 200);
    }
}
