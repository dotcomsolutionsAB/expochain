<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\SuppliersModel;
use App\Models\SuppliersContactsModel;
use App\Models\SupplierAddressModel;
use Illuminate\Support\Str;
use Auth;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Style\Border;
use Maatwebsite\Excel\Style\Alignment;

class SuppliersController extends Controller
{
    // suppliers table
    public function add_suppliers(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:t_suppliers,name',
            'gstin' => 'nullable|string|unique:t_suppliers,gstin',
            'mobile' => 'nullable|string|min:10|max:15',
            'email' => 'nullable|email',

            // Contacts Validation
            'contacts' => 'nullable|array|min:1', // Must be an array if provided
            'contacts.*.name' => 'required_with:contacts|string',
            'contacts.*.designation' => 'nullable|string',
            'contacts.*.mobile' => 'nullable|string|min:10|max:15|unique:t_suppliers_contacts,mobile',
            'contacts.*.email' => 'nullable|email',

            // Addresses Validation
            'addresses' => 'nullable|array|min:1', // Must be an array if provided
            'addresses.*.type' => 'nullable|string|in:billing,shipping', // Must be either "billing" or "shipping"
            'addresses.*.country' => 'nullable|string',
            'addresses.*.address_line_1' => 'nullable|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'nullable|string',
            'addresses.*.state' => 'nullable|string',
            'addresses.*.pincode' => 'nullable|string|min:4|max:10',
        ]);

        $company_id = Auth::user()->company_id;

        // Check if the combination of name, GSTIN, and company_id is unique
        $exists = SuppliersModel::where('name', $request->input('name'))
            ->where('gstin', $request->input('gstin'))
            ->where('company_id', $company_id)
            ->exists();

        if ($exists) {
            return response()->json(['code' => 422, 'success' => false, 'error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
        }

        // Generate unique supplier_id
        $supplier_id = rand(11111111, 99999999);

        // Save contacts
        $contacts = $request->input('contacts');
        $defaultContactId = null;

        foreach ($contacts as $index => $contact) {
            $newContact = SuppliersContactsModel::create([
                'supplier_id' => $supplier_id,
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

        // Save supplier details
        $register_suppliers = SuppliersModel::create([
            'supplier_id' => $supplier_id,
            'company_id' => $company_id,
            'name' => $request->input('name'),
            'gstin' => $request->input('gstin'),
            'mobile' => $request->input('mobile'),
            'email' => $request->input('email'),
            'default_contact' => $defaultContactId,
        ]);

        // Save addresses
        foreach ($request->input('addresses') as $address) {
            SupplierAddressModel::create([
                'company_id' => $company_id,
                'supplier_id' => $supplier_id,
                'type' => $address['type'], // Billing or Shipping
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'],
                'city' => $address['city'],
                'state' => $address['state'],
                'pincode' => $address['pincode'],
                'country' => $address['country'],
            ]);
        }

        unset($register_suppliers['created_at'], $register_suppliers['updated_at']);

        return isset($register_suppliers) && $register_suppliers !== null
            ? response()->json([
                'code' => 201,
                'success' => true,
                'message' => 'Supplier registered successfully!',
                'data' => $register_suppliers,
            ], 201)
            : response()->json(['code' => 400, 'success' => false, 'message' => 'Failed to register supplier record.'], 400);
    }

    // view
    // public function view_suppliers(Request $request)
    // {
    //     // Get filter inputs
    //     $search = $request->input('search'); // Search across multiple fields
    //     $limit = $request->input('limit', 10); // Default limit to 10
    //     $offset = $request->input('offset', 0); // Default offset to 0

    //     // Get total count of records in `t_suppliers`
    //     $total_suppliers = SuppliersModel::count(); 

    //     // Build the query
    //     $suppliersQuery = SuppliersModel::with([
    //         'contacts' => function ($query) use ($search) {
    //             if ($search) {
    //                 $query->where('mobile', 'LIKE', '%' . $search . '%');
    //             }
    //             $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
    //         },
    //         'addresses' => function ($query) {
    //             $query->select('supplier_id', 'type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country');
    //         },
    //     ])
    //     ->select('id', 'supplier_id', 'name', 'gstin', 'company_id')
    //     ->where('company_id', Auth::user()->company_id);

    //     // Apply search filter
    //     if ($search) {
    //         $suppliersQuery->where(function ($query) use ($search) {
    //             $query->where('name', 'LIKE', '%' . $search . '%')
    //                 ->orWhere('gstin', 'LIKE', '%' . $search . '%')
    //                 ->orWhereHas('contacts', function ($q) use ($search) {
    //                     $q->where('mobile', 'LIKE', '%' . $search . '%');
    //                 });
    //         });
    //     }

    //     // Apply limit and offset
    //     $suppliersQuery->offset($offset)->limit($limit);

    //     // Execute the query
    //     $suppliers = $suppliersQuery->get();

    //     // Add contact counts and hide unnecessary fields
    //     $suppliers->each(function ($supplier) {
    //         $supplier->contact_count = $supplier->contacts->count(); // Add contact count
    //         $supplier->contacts->each(function ($contact) {
    //             $contact->makeHidden(['created_at', 'updated_at']);
    //         });
    //     });

    //     // Return the response
    //     return $suppliers->isNotEmpty()
    //         ? response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Suppliers fetched successfully!',
    //             'data' => $suppliers,
    //             'count' => $suppliers->count(), // Total suppliers count
    //             'total_records' => $total_suppliers,
    //         ], 200)
    //         : response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'No suppliers found!',
    //         ], 404);
    // }

    public function view_suppliers(Request $request, $id = null)
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $search = $request->input('search');

        // 🔹 **Fetch Single Supplier by ID**
        if ($id) {
            $supplier = SuppliersModel::with([
                'contacts' => function ($query) {
                    $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
                },
                'addresses' => function ($query) {
                    $query->select('supplier_id', 'type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country');
                },
            ])
            ->where('company_id', Auth::user()->company_id)
            ->where('id', $id)
            ->first();

            if (!$supplier) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Supplier not found!',
                ], 404);
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Supplier fetched successfully!',
                'data' => $supplier,
            ], 200);
        }

        // 🔹 **Fetch All Suppliers with Filters**
        $total_suppliers = SuppliersModel::where('company_id', Auth::user()->company_id)->count(); 

        $suppliersQuery = SuppliersModel::with([
            'contacts' => function ($query) use ($search) {
                if ($search) {
                    $query->where('mobile', 'LIKE', '%' . $search . '%');
                }
                $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
            },
            'addresses' => function ($query) {
                $query->select('supplier_id', 'type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country');
            },
        ])
        ->select('id', 'supplier_id', 'name', 'gstin', 'company_id')
        ->where('company_id', Auth::user()->company_id);

        if ($search) {
            $suppliersQuery->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('gstin', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('contacts', function ($q) use ($search) {
                        $q->where('mobile', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        $suppliersQuery->offset($offset)->limit($limit);
        $suppliers = $suppliersQuery->get();

        if ($suppliers->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No suppliers found!',
            ], 404);
        }

        // Add contact counts
        $suppliers->each(function ($supplier) {
            $supplier->contact_count = $supplier->contacts->count();
            $supplier->contacts->each(function ($contact) {
                $contact->makeHidden(['created_at', 'updated_at']);
            });
        });

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Suppliers fetched successfully!',
            'data' => $suppliers,
            'count' => $suppliers->count(),
            'total_records' => $total_suppliers,
        ], 200);
    }

    public function update_suppliers(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|unique:t_suppliers,name',
            'gstin' => 'nullable|string|unique:t_suppliers,gstin',
            'mobile' => 'nullable|string|min:10|max:15',
            'email' => 'nullable|email',

            // Contacts Validation
            'contacts' => 'nullable|array|min:1', // Must be an array if provided
            'contacts.*.name' => 'required_with:contacts|string',
            'contacts.*.designation' => 'nullable|string',
            'contacts.*.mobile' => 'nullable|string|min:10|max:15|unique:t_suppliers_contacts,mobile',
            'contacts.*.email' => 'nullable|email',

            // Addresses Validation
            'addresses' => 'nullable|array|min:1', // Must be an array if provided
            'addresses.*.type' => 'nullable|string|in:billing,shipping', // Must be either "billing" or "shipping"
            'addresses.*.country' => 'nullable|string',
            'addresses.*.address_line_1' => 'nullable|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'nullable|string',
            'addresses.*.state' => 'nullable|string',
            'addresses.*.pincode' => 'nullable|string|min:4|max:10',
        ]);

        // Fetch the supplier by ID
        $supplier = SuppliersModel::where('id', $id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$supplier) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Supplier not found.'], 404);
        }

        // Update supplier details
        $supplierUpdated = $supplier->update([
            'name' => $request->input('name'),
            'gstin' => $request->input('gstin'),
            'mobile' => $request->input('mobile'),
            'email' => $request->input('email'),
        ]);

        // Update contacts
        $contacts = $request->input('contacts');
        $contactNames = [];
        $contactsUpdated = false;

        foreach ($contacts as $contactData) {
            $contactNames[] = $contactData['name'];

            $contact = SuppliersContactsModel::where('supplier_id', $supplier->supplier_id)
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
                $newContact = SuppliersContactsModel::create([
                    'supplier_id' => $supplier->supplier_id,
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

        // Delete contacts not in the request
        $contactsDeleted = SuppliersContactsModel::where('supplier_id', $supplier->supplier_id)
            ->whereNotIn('name', $contactNames)
            ->delete();

        // Update addresses
        $addresses = $request->input('addresses');
        $addressTypes = [];
        $addressesUpdated = false;

        foreach ($addresses as $addressData) {
            $addressTypes[] = $addressData['type'];

            $address = SupplierAddressModel::where('supplier_id', $supplier->supplier_id)
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
                $newAddress = SupplierAddressModel::create([
                    'company_id' => Auth::user()->company_id,
                    'supplier_id' => $supplier->supplier_id,
                    'type' => $addressData['type'],
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

        // Delete addresses not in the request
        $addressesDeleted = SupplierAddressModel::where('supplier_id', $supplier->supplier_id)
            ->whereNotIn('type', $addressTypes)
            ->delete();

        return ($supplierUpdated || $contactsUpdated || $contactsDeleted || $addressesUpdated || $addressesDeleted)
            ? response()->json(['code' => 200, 'success' => true, 'message' => 'Supplier, contacts, and addresses updated successfully!', 'supplier' => $supplier], 200)
            : response()->json(['code' => 304, 'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_supplier($id)
    {
        // Try to find the client by the given ID
        $get_supplier_id = SuppliersModel::select('supplier_id', 'company_id')
                                        ->where('id', $id)
                                        ->first();
        
        // Check if the client exists
        if ($get_supplier_id && $get_supplier_id->company_id === Auth::user()->company_id)
        {
            // Delete the client
            $delete_supplier = SuppliersModel::where('id', $id)->delete();

            // Delete associated contacts by customer_id
            $delete_contact_records = SuppliersContactsModel::where('supplier_id', $get_supplier_id->supplier_id)->delete();

            // Delete associated address by customer_id
            $delete_address_records = SupplierAddressModel::where('supplier_id', $get_supplier_id->supplier_id)->delete();

            // Return success response if deletion was successful
            return $delete_supplier && $delete_contact_records && $delete_address_records
            ? response()->json(['code' => 200,'success' => true,'message' => 'Supplier and associated contacts and addresses deleted successfully!'], 200)
            : response()->json(['code' => 400,'success' => false,'message' => 'Failed to delete supplier or contacts.'], 400);

        } 
        else 
        {
            // Return error response if supplier not found
            return response()->json(['code' => 404,'success' => false,'message' => 'Supplier not found.'], 404);
        }
    }

    // update address
    public function update_supplier_address(Request $request, $supplier_id)
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

        // Fetch the supplier by ID and company_id
        $supplier = SuppliersModel::where('id', $supplier_id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$supplier) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Supplier not found!',
            ], 404);
        }

        // Check if an address of this type already exists
        $existingAddress = SupplierAddressModel::where('supplier_id', $supplier->supplier_id)
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
            $newAddress = SupplierAddressModel::create([
                'supplier_id' => $supplier->supplier_id,
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
    public function update_supplier_gst(Request $request, $supplier_id)
    {
        // Validate the request input
        $request->validate([
            'gst' => 'required|string',
        ]);

        // Fetch the supplier by ID and company_id
        $supplier = SuppliersModel::where('id', $supplier_id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$supplier) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Supplier not found!',
            ], 404);
        }

            // ✅ **Update GST if the supplier exists**
            $supplier->update([
                'gstin' => $request->input('gst')
            ]);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Supplier GST updated successfully!',
                'data' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'gst' => $supplier->gstin
                ]
            ], 200);
    }

    // migrate
    public function importSuppliersData()
    {
        SuppliersModel::truncate();
        SuppliersContactsModel::truncate();
        SupplierAddressModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/suppliers.php';

        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];
        $company_id = Auth::user()->company_id;

        foreach ($data as $record) {
            $existingSuppliers = SuppliersModel::where('name', $record['name'])->first();
            if ($existingSuppliers) {
                continue; // Skip if supplier already exists
            }

            // Process and normalize mobile numbers
            $rawMobileData = $record['mobile'] ?? '';
            $mobileList = $this->processMobileNumbers($rawMobileData); // Use helper function to process numbers
            // $primaryMobile = $mobileList[0] ?? '0000000000'; // First number as the primary mobile

            // $primaryEmail = filter_var(trim($record['email'] ?? ''), FILTER_VALIDATE_EMAIL)
            //     ? trim($record['email'])
            //     : 'placeholder_' . now()->timestamp . '@example.com';

            // Generate unique supplier ID
            do {
                // $supplier_id = rand(1111111111, 9999999999);
                $supplier_id = rand(11111111, 99999999);
                $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
            } while ($exists);

            // Insert supplier record
            try {
                $register_supplier = SuppliersModel::create([
                    'supplier_id' => $supplier_id,
                    'company_id' => $company_id,
                    'name' => $record['name'],
                    // 'gstin' => $record['GSTIN'] ?? 'Random GSTIN' . now()->timestamp . '_' . Str::random(5),
                    'gstin' => $record['gstin'],
                    // 'contacts' => json_encode(['mobile' => $rawMobileData, 'email' => $record['email']]), // Store raw contact details as JSON
                    'contacts' => json_encode(['mobile' => $record['mobile'], 'email' => $record['email']]), // Store raw contact details as JSON
                    // 'mobile' => $primaryMobile, // Store the first parsed mobile number
                    // 'email' => $primaryEmail,
                    'mobile' => $record['mobile'], // Store the first parsed mobile number
                    'email' => $record['email'],
                ]);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert supplier: ' . $e->getMessage()];
                continue;
            }

            // Decode JSON address field safely
            $addressData = json_decode($record['address'], true);

            // Check if decoding failed and set default values
            if (!is_array($addressData)) {
                $addressData = [
                    'address1' => null,
                    'address2' => null,
                    'city' => null,
                    'pincode' => null
                ];
            }


            // Insert address into SupplierAddressModel
            try {
                SupplierAddressModel::create([
                    'company_id' => $company_id,
                    'supplier_id' => $register_supplier->supplier_id,
                    // 'address_line_1' => $addressData['address1'] ?? 'Default Address Line 1',
                    // 'address_line_2' => $addressData['address2'] ?? 'Default Address Line 2',
                    // 'city' => $addressData['city'] ?? 'Default City',
                    // 'pincode' => $addressData['pincode'] ?? '000000',
                    // 'state' => $record['state'] ?? 'Unknown State',
                    'address_line_1' => $addressData['address1'] ?? null,
                    'address_line_2' => $addressData['address2'] ?? null,
                    'city' => $addressData['city'] ?? null,
                    'pincode' => $addressData['pincode'] ?? null,
                    'state' => $record['state'] ?? null,
                    'country' => $record['country'] ?? null,
                ]);
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert address: ' . $e->getMessage()];
            }

            // Insert all parsed mobile numbers into SuppliersContactsModel
            foreach ($mobileList as $mobile) {
                try {
                    SuppliersContactsModel::create([
                        'supplier_id' => $register_supplier->supplier_id,
                        'company_id' => $company_id,
                        'name' => $record['name'], // Use supplier name as the default contact name
                        'designation' => 'Default Designation', // Default designation
                        'mobile' => $mobile, // Store each parsed mobile number
                        // 'email' => $primaryEmail, // Use primary email for contacts
                        'email' => $record['email'], // Use primary email for contacts
                    ]);
                } catch (\Exception $e) {
                    $errors[] = ['record' => $mobile, 'error' => 'Failed to insert contact: ' . $e->getMessage()];
                }
            }
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

    // Helper function to process and normalize mobile numbers
    private function processMobileNumbers($rawMobileData)
    {
        $mobileList = [];
        $areaCode = ''; // Variable to track the last detected area code

        if (!empty($rawMobileData)) {
            // Split the input by commas and slashes
            $mobileParts = explode(',', $rawMobileData);

            foreach ($mobileParts as $group) {
                $group = trim($group);
                $subParts = explode('/', $group);

                foreach ($subParts as $part) {
                    $part = trim($part);

                    // If the part contains an area code (e.g., "033 22489216")
                    if (preg_match('/^\d{2,4}\s\d+$/', $part)) {
                        $mobileList[] = $part; // Add full number with area code
                        $areaCode = explode(' ', $part)[0]; // Extract the area code for future use
                    } elseif (preg_match('/^\d+$/', $part)) {
                        // If the part is a number without an area code
                        if (!empty($areaCode)) {
                            $mobileList[] = $areaCode . ' ' . $part; // Prepend the last known area code
                        } else {
                            $mobileList[] = $part; // Add as is if no area code is available
                        }
                    }
                }
            }
        }

        // Remove duplicates and ensure clean numbers
        return array_unique(array_map('trim', $mobileList));
    }

    // public function export_suppliers(Request $request)
    // {
    //     // Check for comma-separated IDs
    //     $ids = $request->input('id') ? explode(',', $request->input('id')) : null;
    //     $search = $request->input('search'); // Optional search input

    //     $suppliersQuery = SuppliersModel::with([
    //         'contacts' => function ($query) {
    //             $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
    //         },
    //         'addresses' => function ($query) {
    //             $query->select('supplier_id', 'type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country');
    //         },
    //     ])
    //     ->select('supplier_id', 'name', 'gstin', 'company_id')
    //     ->where('company_id', Auth::user()->company_id);

    //     // If IDs are provided, prioritize them
    //     if ($ids) {
    //         $suppliersQuery->whereIn('id', $ids);
    //     } elseif ($search) {
    //         // Apply search filter if IDs are not provided
    //         $suppliersQuery->where(function ($query) use ($search) {
    //             $query->where('name', 'LIKE', '%' . $search . '%')
    //                 ->orWhere('gstin', 'LIKE', '%' . $search . '%')
    //                 ->orWhereHas('contacts', function ($q) use ($search) {
    //                     $q->where('mobile', 'LIKE', '%' . $search . '%');
    //                 });
    //         });
    //     }

    //     $suppliers = $suppliersQuery->get();

    //     if ($suppliers->isEmpty()) {
    //         return response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'No suppliers found to export!',
    //         ], 404);
    //     }

    //     // Format data for Excel
    //     $exportData = $suppliers->map(function ($supplier) {
    //         return [
    //             'Supplier ID' => $supplier->supplier_id,
    //             'Name' => $supplier->name,
    //             'GSTIN' => $supplier->gstin,
    //             // 'Contacts' => $supplier->contacts->map(fn($contact) => "{$contact->name} ({$contact->mobile})")->join(', '),
    //             'Address' => $supplier->addresses->map(fn($address) => "{$address->address_line_1}, {$address->city}, {$address->state}, {$address->pincode}, {$address->country}")->join('; '),
    //         ];
    //     })->toArray();

    //     // Generate the file path
    //     $fileName = 'suppliers_export_' . now()->format('Ymd_His') . '.xlsx';
    //     $filePath = 'uploads/suppliers_excel/' . $fileName;

    //     // Save Excel to storage
    //     Excel::store(new class($exportData) implements FromCollection, WithHeadings {
    //         private $data;

    //         public function __construct(array $data)
    //         {
    //             $this->data = collect($data);
    //         }

    //         public function collection()
    //         {
    //             return $this->data;
    //         }

    //         public function headings(): array
    //         {
    //             return [
    //                 'Supplier ID',
    //                 'Name',
    //                 'GSTIN',
    //                 // 'Contacts',
    //                 'Address',
    //             ];
    //         }
    //     }, $filePath, 'public');

    //     // Get file details
    //     $fileUrl = asset('storage/' . $filePath);
    //     $fileSize = Storage::disk('public')->size($filePath);
    //     // $contentType = Storage::disk('public')->mimeType($filePath);

    //     // Return response with file details
    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => 'File available for download',
    //         'data' => [
    //             'file_url' => $fileUrl,
    //             'file_name' => $fileName,
    //             'file_size' => $fileSize,
    //             // 'content_type' => $contentType,
    //             'content_type' => "Excel",
    //         ],
    //     ], 200);
    // }

    // export
    public function export_suppliers(Request $request)
    {
        // Check for comma-separated IDs
        $ids = $request->input('id') ? explode(',', $request->input('id')) : null;
        $search = $request->input('search'); // Optional search input

        $suppliersQuery = SuppliersModel::with([
            'contacts' => function ($query) {
                $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
            },
            'addresses' => function ($query) {
                $query->select('supplier_id', 'type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country');
            },
        ])
        ->select('supplier_id', 'name', 'gstin', 'company_id')
        ->where('company_id', Auth::user()->company_id);

        // If IDs are provided, prioritize them
        if ($ids) {
            $suppliersQuery->whereIn('id', $ids);
        } elseif ($search) {
            // Apply search filter if IDs are not provided
            $suppliersQuery->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('gstin', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('contacts', function ($q) use ($search) {
                        $q->where('mobile', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        $suppliers = $suppliersQuery->get();

        if ($suppliers->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No suppliers found to export!',
            ], 404);
        }

        // Format data for Excel with serial number and bold columns
        $exportData = $suppliers->map(function ($supplier, $index) {
            // Get the first address's state
            $address = $supplier->addresses->first();
            $addressText = $address ? "{$address->address_line_1}, {$address->city}, {$address->state}, {$address->pincode}, {$address->country}" : '';

            return [
                'Sl. No.' => $index + 1,  // Serial Number
                'Name' => $supplier->name,
                'Mobile' => $supplier->contacts->first()->mobile ?? '', // Mobile from first contact
                'Email' => $supplier->contacts->first()->email ?? '',  // Email from first contact
                'GSTIN' => $supplier->gstin,
                'Address' => $addressText,
            ];
        })->toArray();

        // Generate the file path
        $fileName = 'suppliers_export_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'uploads/suppliers_excel/' . $fileName;

        // Save Excel to storage with bold headers and borders
        Excel::store(new class($exportData) implements FromCollection, WithHeadings, WithStyles {
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
                    'Sl. No.', 'Name', 'Mobile', 'Email', 'GSTIN', 'Address',
                ];
            }

            public function styles(Worksheet $sheet)
            {
                // Apply bold style to headings
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);

                // Center align the headers and data
                $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2:F' . (count($this->data) + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Apply borders to all the cells
                $sheet->getStyle('A1:F' . (count($this->data) + 1))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Set column width to adjust content
                foreach (range('A', 'F') as $columnID) {
                    $sheet->getColumnDimension($columnID)->setAutoSize(true);
                }
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
                'content_type' => "Excel",
            ],
        ], 200);
    }
}
