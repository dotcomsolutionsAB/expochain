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

class SuppliersController extends Controller
{
    //
    // suppliers table
    //create
    // public function add_suppliers(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|unique:t_suppliers,name',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required|string',
    //         'state' => 'required|string',
    //         'country' => 'required|string',
    //         'gstin' => 'required|string|unique:t_suppliers,gstin',
    //         'contacts' => 'required',
    //     ]);

    //     $company_id = Auth::user()->company_id;

    //     // Check if the combination of name, gstin, and contact_id is unique
    //     $exists = SuppliersModel::where('name', $request->input('name'))
    //                     ->where('gstin', $request->input('gstin'))
    //                     ->where('company_id', $company_id)
    //                     ->exists();

    //     if ($exists) {
    //         return response()->json(['error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
    //     }

    //     do {
    //         $supplier_id = rand(1111111111,9999999999);

    //         $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
    //     } while ($exists);

    //     $contacts = $request->input('contacts');

    //     // Iterate over the contacts array and insert each contact
    //     foreach ($contacts as $contact) {
    //             SuppliersContactsModel::create([
    //             'supplier_id' => $supplier_id,
    //             'company_id' => $company_id,
    //             'name' => $contact['name'],
    //             'designation' => $contact['designation'],
    //             'mobile' => $contact['mobile'],
    //             'email' => $contact['email'],
    //         ]);
    //     }

    //     $register_suppliers = SuppliersModel::create([
    //         'supplier_id' => $supplier_id,
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $request->input('name'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'gstin' => $request->input('gstin'),
    //     ]);
        
    //     unset($register_suppliers['id'], $register_suppliers['created_at'], $register_suppliers['updated_at']);

    //     return isset($register_suppliers) && $register_suppliers !== null
    //     ? response()->json(['code' => 201,'success' => true, 'Suppliers registered successfully!', 'data' => $register_suppliers], 201)
    //     : response()->json(['code' => 400,'success' => false,'Failed to register Suppliers record'], 400);
    // }

    // public function add_suppliers(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|unique:t_suppliers,name',
    //         'gstin' => 'required|string|unique:t_suppliers,gstin',
    //         'contacts' => 'required|array|min:1',
    //         'contacts.*.name' => 'required|string',
    //         'contacts.*.designation' => 'required|string',
    //         'contacts.*.mobile' => 'required|string',
    //         'contacts.*.email' => 'required|email',
    //         'addresses' => 'required|array|min:1',
    //         'addresses.*.type' => 'required|in:billing,shipping',
    //         'addresses.*.address_line_1' => 'required|string',
    //         'addresses.*.address_line_2' => 'nullable|string',
    //         'addresses.*.city' => 'required|string',
    //         'addresses.*.state' => 'required|string',
    //         'addresses.*.pincode' => 'required|string',
    //         'addresses.*.country' => 'required|string',
    //     ]);

    //     $company_id = Auth::user()->company_id;

    //     // Check if the combination of name and GSTIN is unique
    //     $exists = SuppliersModel::where('name', $request->input('name'))
    //         ->where('gstin', $request->input('gstin'))
    //         ->where('company_id', $company_id)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json(['error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
    //     }

    //     // Generate unique supplier_id
    //     do {
    //         $supplier_id = rand(1111111111, 9999999999);
    //         $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
    //     } while ($exists);

    //     // Save supplier details
    //     $register_suppliers = SuppliersModel::create([
    //         'supplier_id' => $supplier_id,
    //         'company_id' => $company_id,
    //         'name' => $request->input('name'),
    //         'gstin' => $request->input('gstin'),
    //     ]);

    //     // Save contacts
    //     $contacts = $request->input('contacts');
    //     foreach ($contacts as $contact) {
    //         SuppliersContactsModel::create([
    //             'supplier_id' => $supplier_id,
    //             'company_id' => $company_id,
    //             'name' => $contact['name'],
    //             'designation' => $contact['designation'],
    //             'mobile' => $contact['mobile'],
    //             'email' => $contact['email'],
    //         ]);
    //     }

    //     // Save addresses
    //     $addresses = $request->input('addresses');
    //     foreach ($addresses as $address) {
    //         SupplierAddressModel::create([
    //             'company_id' => $company_id,
    //             'supplier_id' => $supplier_id,
    //             'type' => $address['type'], // Billing or Shipping
    //             'address_line_1' => $address['address_line_1'],
    //             'address_line_2' => $address['address_line_2'],
    //             'city' => $address['city'],
    //             'state' => $address['state'],
    //             'pincode' => $address['pincode'],
    //             'country' => $address['country'],
    //         ]);
    //     }

    //     return response()->json([
    //         'code' => 201,
    //         'success' => true,
    //         'message' => 'Supplier registered successfully!',
    //         'data' => $register_suppliers,
    //     ], 201);
    // }

    public function add_suppliers(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:t_suppliers,name',
            'gstin' => 'required|string|unique:t_suppliers,gstin',
            'mobile' => 'required|string|size:13',
            'email' => 'required|email',
            'contacts' => 'required|array|min:1',
            'contacts.*.name' => 'required|string',
            'contacts.*.designation' => 'required|string',
            'contacts.*.mobile' => 'required|string',
            'contacts.*.email' => 'required|email',
            'addresses' => 'required|array|min:1',
            'addresses.*.type' => 'required|in:billing,shipping',
            'addresses.*.address_line_1' => 'required|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.state' => 'required|string',
            'addresses.*.pincode' => 'required|string',
            'addresses.*.country' => 'required|string',
        ]);

        $company_id = Auth::user()->company_id;

        // Check if the combination of name, GSTIN, and company_id is unique
        $exists = SuppliersModel::where('name', $request->input('name'))
            ->where('gstin', $request->input('gstin'))
            ->where('company_id', $company_id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'The combination of name, GSTIN, and company ID must be unique.'], 422);
        }

        // Generate unique supplier_id
        $supplier_id = rand(1111111111, 9999999999);

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
    // public function view_suppliers()
    // {        
    //     $get_suppliers = SuppliersModel::with(['contact' => function ($query)
    //     {
    //         $query->select('supplier_id','name','designation','mobile','email');
    //     }])
    //     ->select('supplier_id','name','address_line_1','address_line_2', 'city', 'pincode', 'state', 'country','gstin')
    //     ->where('company_id',Auth::user()->company_id)
    //     ->get();
        

    //     return isset($get_suppliers) && $get_suppliers !== null
    //     ? response()->json(['Fetch record successfully!', 'data' => $get_suppliers], 200)
    //     : response()->json(['Failed to fetch data'], 404); 
    // }

    // public function view_suppliers(Request $request)
    // {
    //     // Get filter inputs
    //     $limit = $request->input('limit', 10); // Default limit to 10
    //     $offset = $request->input('offset', 0); // Default offset to 0
    //     $name = $request->input('name'); // Optional name filter
    //     $gstin = $request->input('gstin'); // Optional GSTIN filter
    //     $mobile = $request->input('mobile'); // Optional mobile filter

    //     // Build the query
    //     $query = SuppliersModel::with(['contact' => function ($query) use ($mobile) {
    //         $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
    //         if ($mobile) {
    //             $query->where('mobile', 'LIKE', '%' . $mobile . '%');
    //         }
    //     }])
    //     ->select('supplier_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'gstin')
    //     ->where('company_id', Auth::user()->company_id);

    //     // Apply filters
    //     if ($name) {
    //         $query->where('name', 'LIKE', '%' . $name . '%');
    //     }
    //     if ($gstin) {
    //         $query->where('gstin', 'LIKE', '%' . $gstin . '%');
    //     }

    //     // Apply limit and offset
    //     $query->offset($offset)->limit($limit);

    //     // Execute the query
    //     $get_suppliers = $query->get();

    //     // Return the response
    //     return $get_suppliers->isNotEmpty()
    //         ? response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Fetch record successfully!',
    //             'data' => $get_suppliers,
    //             'count' => $get_suppliers->count()
    //         ], 200)
    //         : response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'No suppliers found!',
    //         ], 404);
    // }

    // public function view_suppliers(Request $request)
    // {
    //     // Get filter inputs
    //     $limit = $request->input('limit', 10); // Default limit to 10
    //     $offset = $request->input('offset', 0); // Default offset to 0
    //     $name = $request->input('name'); // Optional name filter
    //     $gstin = $request->input('gstin'); // Optional GSTIN filter
    //     $mobile = $request->input('mobile'); // Optional mobile filter

    //     // Build the query
    //     $query = SuppliersModel::query()
    //         ->with([
    //             'contacts' => function ($query) use ($mobile) {
    //                 $query->select('supplier_id', 'name', 'designation', 'mobile', 'email');
    //                 if ($mobile) {
    //                     $query->where('mobile', 'LIKE', '%' . $mobile . '%');
    //                 }
    //             },
    //             'addresses' => function ($query) {
    //                 $query->select('supplier_id', 'type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country');
    //             }
    //         ])
    //         ->where('company_id', Auth::user()->company_id);

    //     // Apply filters
    //     if ($name) {
    //         $query->where('name', 'LIKE', '%' . $name . '%');
    //     }
    //     if ($gstin) {
    //         $query->where('gstin', 'LIKE', '%' . $gstin . '%');
    //     }

    //     // Apply limit and offset
    //     $query->offset($offset)->limit($limit);

    //     // Execute the query and hide `created_at` and `updated_at`
    //     $get_suppliers = $query->get()->makeHidden(['created_at', 'updated_at']);

    //     // Return the response
    //     return $get_suppliers->isNotEmpty()
    //         ? response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Suppliers fetched successfully!',
    //             'data' => $get_suppliers,
    //             'count' => $get_suppliers->count()
    //         ], 200)
    //         : response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'No suppliers found!',
    //         ], 404);
    // }
    public function view_suppliers(Request $request)
    {
        // Get filter inputs
        $search = $request->input('search'); // Search across multiple fields
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
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

        // Apply search filter
        if ($search) {
            $suppliersQuery->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('gstin', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('contacts', function ($q) use ($search) {
                        $q->where('mobile', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        // Apply limit and offset
        $suppliersQuery->offset($offset)->limit($limit);

        // Execute the query
        $suppliers = $suppliersQuery->get();

        // Add contact counts and hide unnecessary fields
        $suppliers->each(function ($supplier) {
            $supplier->contact_count = $supplier->contacts->count(); // Add contact count
            $supplier->contacts->each(function ($contact) {
                $contact->makeHidden(['created_at', 'updated_at']);
            });
        });

        // Return the response
        return $suppliers->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Suppliers fetched successfully!',
                'data' => $suppliers,
                'total_contacts' => $suppliers->sum(fn($supplier) => $supplier->contacts->count()), // Total contact count
                'count' => $suppliers->count(), // Total suppliers count
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No suppliers found!',
            ], 404);
    }




    // update
    // public function update_suppliers(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'supplier_id' => 'required',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required|string',
    //         'state' => 'required|string',
    //         'country' => 'required|string',
    //         'gstin' => 'required|string',
    //         'contacts' => 'required|array',
    //         'contacts.*.name' => 'required|string',
    //         'contacts.*.designation' => 'required|string',
    //         'contacts.*.mobile' => 'required|string',
    //         'contacts.*.email' => 'required|email',
    //     ]);

    //     // get the client
    //     $suppliers = SuppliersModel::where('supplier_id', $request->input('supplier_id'))->first();

    //     // Update the client information
    //     $suppliersUpdated = $suppliers->update([
    //         'name' => $request->input('name'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'gstin' => $request->input('gstin'),
    //     ]);

    //     // Get the list of contacts from the request
    //     $contacts = $request->input('contacts');

    //     // Collect names of contacts that are in the request
    //     $requestContactNames = [];

    //     $contactsUpdated = false;

    //     foreach ($contacts as $contactData) 
    //     {
    //         $requestContactNames[] = $contactData['name'];

    //         // Check if the contact exists by customer_id and name
    //         $contact = SuppliersContactsModel::where('supplier_id', $contactData['supplier_id'])
    //                                         ->where('name',$contactData['name'])
    //                                         ->first();

    //         if ($contact) 
    //         {
    //             // Update the existing contact
    //             $contactsUpdated = $contact->update([
    //                 'designation' => $contactData['designation'],
    //                 'mobile' => $contactData['mobile'],
    //                 'email' => $contactData['email'],
    //             ]);
    //         }
    //         else
    //         {
    //             // Create a new contact since it doesn't exist
    //             $newContact = SuppliersContactsModel::create([
    //                 'supplier_id' => $client->supplier_id,
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $contactData['name'],
    //                 'designation' => $contactData['designation'],
    //                 'mobile' => $contactData['mobile'],
    //                 'email' => $contactData['email'],
    //             ]);

    //             if ($newContact) {
    //                 $contactsUpdated = true; // New contact created successfully
    //             }
    //         }
    //     }

    //     // Delete contacts that are not present in the request but exist in the database for this customer_id
    //     $contactsDeleted = SuppliersContactsModel::where('supplier_id', $suppliers->supplier_id)
    //     ->whereNotIn('name', $requestContactNames)  // Delete if name is not in the request
    //     ->delete();

    //     unset($suppliers['id'], $suppliers['created_at'], $suppliers['updated_at']);

    //     return ($suppliersUpdated || $contactsUpdated || $contactsDeleted)
    //     ? response()->json(['code' => 200,'success' => true,'message' => 'Client and contacts updated successfully!', 'client' => $suppliers], 200)
    //     : response()->json(['code' => 304,'success' => false,'message' => 'No changes detected.'], 304);
    // }

    // public function update_suppliers(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'supplier_id' => 'required',
    //         'gstin' => 'required|string',
    //         'contacts' => 'required|array|min:1',
    //         'contacts.*.name' => 'required|string',
    //         'contacts.*.designation' => 'required|string',
    //         'contacts.*.mobile' => 'required|string',
    //         'contacts.*.email' => 'required|email',
    //         'addresses' => 'required|array|min:1',
    //         'addresses.*.type' => 'required|in:billing,shipping',
    //         'addresses.*.address_line_1' => 'required|string',
    //         'addresses.*.address_line_2' => 'nullable|string',
    //         'addresses.*.city' => 'required|string',
    //         'addresses.*.state' => 'required|string',
    //         'addresses.*.pincode' => 'required|string',
    //         'addresses.*.country' => 'required|string',
    //     ]);

    //     // Get the supplier record
    //     $supplier = SuppliersModel::where('supplier_id', $request->input('supplier_id'))->first();

    //     if (!$supplier) {
    //         return response()->json(['code' => 404, 'success' => false, 'message' => 'Supplier not found.'], 404);
    //     }

    //     // Update supplier details
    //     $supplierUpdated = $supplier->update([
    //         'name' => $request->input('name'),
    //         'gstin' => $request->input('gstin'),
    //     ]);

    //     // Update contacts
    //     $contacts = $request->input('contacts');
    //     $contactNames = [];
    //     $contactsUpdated = false;

    //     foreach ($contacts as $contactData) {
    //         $contactNames[] = $contactData['name'];

    //         $contact = SuppliersContactsModel::where('supplier_id', $supplier->supplier_id)
    //                                         ->where('name', $contactData['name'])
    //                                         ->first();

    //         if ($contact) {
    //             // Update existing contact
    //             $contactsUpdated = $contact->update([
    //                 'designation' => $contactData['designation'],
    //                 'mobile' => $contactData['mobile'],
    //                 'email' => $contactData['email'],
    //             ]);
    //         } else {
    //             // Create a new contact
    //             $newContact = SuppliersContactsModel::create([
    //                 'supplier_id' => $supplier->supplier_id,
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $contactData['name'],
    //                 'designation' => $contactData['designation'],
    //                 'mobile' => $contactData['mobile'],
    //                 'email' => $contactData['email'],
    //             ]);

    //             if ($newContact) {
    //                 $contactsUpdated = true;
    //             }
    //         }
    //     }

    //     // Delete contacts not in the request
    //     $contactsDeleted = SuppliersContactsModel::where('supplier_id', $supplier->supplier_id)
    //                                             ->whereNotIn('name', $contactNames)
    //                                             ->delete();

    //     // Update addresses
    //     $addresses = $request->input('addresses');
    //     $addressTypes = [];
    //     $addressesUpdated = false;

    //     foreach ($addresses as $addressData) {
    //         $addressTypes[] = $addressData['type'];

    //         $address = SupplierAddressModel::where('supplier_id', $supplier->supplier_id)
    //                                     ->where('type', $addressData['type'])
    //                                     ->first();

    //         if ($address) {
    //             // Update existing address
    //             $addressesUpdated = $address->update([
    //                 'address_line_1' => $addressData['address_line_1'],
    //                 'address_line_2' => $addressData['address_line_2'],
    //                 'city' => $addressData['city'],
    //                 'state' => $addressData['state'],
    //                 'pincode' => $addressData['pincode'],
    //                 'country' => $addressData['country'],
    //             ]);
    //         } else {
    //             // Create a new address
    //             $newAddress = SupplierAddressModel::create([
    //                 'company_id' => Auth::user()->company_id,
    //                 'supplier_id' => $supplier->supplier_id,
    //                 'type' => $addressData['type'],
    //                 'address_line_1' => $addressData['address_line_1'],
    //                 'address_line_2' => $addressData['address_line_2'],
    //                 'city' => $addressData['city'],
    //                 'state' => $addressData['state'],
    //                 'pincode' => $addressData['pincode'],
    //                 'country' => $addressData['country'],
    //             ]);

    //             if ($newAddress) {
    //                 $addressesUpdated = true;
    //             }
    //         }
    //     }

    //     // Delete addresses not in the request
    //     $addressesDeleted = SupplierAddressModel::where('supplier_id', $supplier->supplier_id)
    //                                             ->whereNotIn('type', $addressTypes)
    //                                             ->delete();

    //     return ($supplierUpdated || $contactsUpdated || $contactsDeleted || $addressesUpdated || $addressesDeleted)
    //         ? response()->json(['code' => 200, 'success' => true, 'message' => 'Supplier, contacts, and addresses updated successfully!', 'supplier' => $supplier], 200)
    //         : response()->json(['code' => 304, 'success' => false, 'message' => 'No changes detected.'], 304);
    // }

    public function update_suppliers(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'gstin' => 'required|string',
            'mobile' => 'required|string|size:13',
            'email' => 'required|email',
            'contacts' => 'required|array|min:1',
            'contacts.*.name' => 'required|string',
            'contacts.*.designation' => 'required|string',
            'contacts.*.mobile' => 'required|string',
            'contacts.*.email' => 'required|email',
            'addresses' => 'required|array|min:1',
            'addresses.*.type' => 'required|in:billing,shipping',
            'addresses.*.address_line_1' => 'required|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.state' => 'required|string',
            'addresses.*.pincode' => 'required|string',
            'addresses.*.country' => 'required|string',
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

    // migrate
    // public function importSuppliersData()
    // {
    //     // SuppliersModel::truncate();  
        
    //     // SuppliersContactsModel::truncate();  

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/suppliers.php'; // Replace with the actual URL

    //     try {
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
    //     }
    
    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }
    
    //     $data = $response->json('data');
    
    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }
    
    //     $successfulInserts = 0;
    //     $errors = [];
    
    //     foreach ($data as $record) {
    //         $existingSuppliers = SuppliersModel::where('name', $record['name'])->first();
    //         if ($existingSuppliers) {
    //             continue; // Skip if supplier already exists
    //         }

    //          // Decode JSON-encoded fields if they are not empty
    //         $addressData = json_decode($record['address'], true) ?? [];

    //         // Set default values for address fields if missing
    //         $validationData = [
    //             'name' => $record['name'],
    //             'address_line_1' => !empty($addressData['address1']) ? $addressData['address1'] : 'Default Address Line 1',
    //             'address_line_2' => !empty($addressData['address2']) ? $addressData['address2'] : 'Default Address Line 2',
    //             'city' => !empty($addressData['city']) ? $addressData['city'] : 'Default City',
    //             'pincode' =>  !empty($addressData['pincode']) ? $addressData['pincode'] : '000000',
    //             'state' => !empty($record['state']) ? $record['state'] : 'Unknown State',
    //             'country' => !empty($record['country']) ? $record['country'] : 'India',
    //             'gstin' => !empty($record['GSTIN']) ? $record['GSTIN'] : 'Random GSTIN' . now()->timestamp . '_' . Str::random(5),
    //             'contacts' => [['name' => $record['name'], 'mobile' => $record['mobile'], 'email' => $record['email']]],
    //         ];   
            
    //         dd($validationData);
    
    //         // Validate the record
    //         $validator = Validator::make($validationData, [
    //             'name' => 'required|string|unique:t_suppliers,name',
    //             'address_line_1' => 'required|string',
    //             'address_line_2' => 'required|string',
    //             'city' => 'required|string',
    //             'pincode' => 'required|string',
    //             'state' => 'required|string',
    //             'country' => 'required|string',
    //             'gstin' => 'required|string|unique:t_suppliers,gstin',
    //             'contacts' => 'required|array',
    //         ]);
    
    //         if ($validator->fails()) {
    //             $errors[] = ['record' => $record, 'errors' => $validator->errors()];
    //             continue;
    //         }
    
    //         do {
    //         // Generate unique supplier ID
    //         $supplier_id = rand(1111111111, 9999999999);
            
    //         $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
    //     } while ($exists);
    
    //         // Insert supplier record
    //         try {
    //             $register_supplier = SuppliersModel::create([
    //                 'supplier_id' => $supplier_id,
    //                 'name' => $validationData['name'],
    //                 'address_line_1' => $validationData['address_line_1'],
    //                 'address_line_2' => $validationData['address_line_2'],
    //                 'city' => $validationData['city'],
    //                 'pincode' => $validationData['pincode'],
    //                 'state' => $validationData['state'],
    //                 'country' => $validationData['country'],
    //                 'gstin' => $validationData['gstin'],
    //             ]);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert supplier: ' . $e->getMessage()];
    //             continue;
    //         }

    //        // Insert contact record using data from the supplier record itself
    //         try {
    //             SuppliersContactsModel::create([
    //                 'supplier_id' => $register_supplier->supplier_id,
    //                 'name' => $record['name'],
    //                 'designation' => 'Default Designation',
    //                 'mobile' => $record['mobile'] ?? '0000000000',
    //                 'email' => filter_var($record['email'], FILTER_VALIDATE_EMAIL) ? $record['email'] : 'placeholder_' . now()->timestamp . '@example.com',
    //             ]);
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert contact: ' . $e->getMessage()];
    //         }
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Data import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    // public function importSuppliersData()
    // {
    //     SuppliersModel::truncate();
    //     SuppliersContactsModel::truncate();
    //     SupplierAddressModel::truncate();

    //     $url = 'https://expo.egsm.in/assets/custom/migrate/suppliers.php';

    //     try {
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $successfulInserts = 0;
    //     $errors = [];

    //     $company_id = Auth::user()->company_id;

    //     foreach ($data as $record) {
    //         $existingSuppliers = SuppliersModel::where('name', $record['name'])->first();
    //         if ($existingSuppliers) {
    //             continue; // Skip if supplier already exists
    //         }

    //         $addressData = json_decode($record['address'], true) ?? [];

    //         // Extract first mobile and email for SupplierModel
    //         // $mobileList = explode(',', $record['mobile'] ?? '');
    //         $emailList = explode(',', $record['email'] ?? '');

    //         $mobileList = [];
    //         $rawMobileData = $record['mobile'] ?? '';
    //         $areaCode = ''; // Initialize an empty area code variable

    //         // Parse the raw mobile data
    //         if (!empty($rawMobileData)) {
    //             $rawMobileParts = explode('/', $rawMobileData); // Split by '/'
    //             foreach ($rawMobileParts as $part) {
    //                 $part = trim($part);
            
    //                 // If the part contains an area code (e.g., "022 28367314")
    //                 if (preg_match('/^\d{2,4}\s\d+$/', $part)) {
    //                     $mobileList[] = $part; // Add full number with area code
    //                     $areaCode = explode(' ', $part)[0]; // Extract area code for future use
    //                 } elseif (preg_match('/^\d+$/', $part)) {
    //                     // If the part is a number without an area code
    //                     if (!empty($areaCode)) {
    //                         $mobileList[] = $areaCode . ' ' . $part; // Prepend the last known area code
    //                     } else {
    //                         $mobileList[] = $part; // Add as is if no area code is available
    //                     }
    //                 } elseif (preg_match('/^\d+\/\d+$/', $part)) {
    //                     // Handle numbers like "28367312/13"
    //                     $baseNumber = explode('/', $part)[0];
    //                     $lastDigits = explode('/', $part)[1];
            
    //                     // Generate full numbers for each digit in the range
    //                     if (!empty($areaCode)) {
    //                         $mobileList[] = $areaCode . ' ' . $baseNumber; // Add the base number with area code
    //                         $mobileList[] = $areaCode . ' ' . substr($baseNumber, 0, -strlen($lastDigits)) . $lastDigits; // Add the derived number
    //                     } else {
    //                         $mobileList[] = $baseNumber; // Add the base number
    //                         $mobileList[] = substr($baseNumber, 0, -strlen($lastDigits)) . $lastDigits; // Add the derived number
    //                     }
    //                 }
    //             }
    //         }

    //         // Remove duplicates and ensure clean numbers
    //         $mobileList = array_unique(array_map('trim', $mobileList));

    //         // Get the primary mobile number (first in the list)
    //         $primaryMobile = $mobileList[0] ?? '0000000000';


    //         // $primaryMobile = trim($mobileList[0] ?? '0000000000');
    //         // $primaryMobile = $mobileList[0] ?? '0000000000';
    //         $primaryEmail = filter_var(trim($emailList[0] ?? ''), FILTER_VALIDATE_EMAIL)
    //             ? trim($emailList[0])
    //             : 'placeholder_' . now()->timestamp . '@example.com';

    //         // Generate unique supplier ID
    //         do {
    //             $supplier_id = rand(1111111111, 9999999999);
    //             $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
    //         } while ($exists);

    //         // Insert supplier record
    //         try {
    //             $register_supplier = SuppliersModel::create([
    //                 'supplier_id' => $supplier_id,
    //                 'company_id' => $company_id,
    //                 'name' => $record['name'],
    //                 'gstin' => $record['GSTIN'] ?? 'Random GSTIN' . now()->timestamp . '_' . Str::random(5),
    //                 'contacts' => json_encode(['mobile' => $record['mobile'], 'email' => $record['email']]), // Store full contact details as JSON
    //                 'mobile' => $primaryMobile,
    //                 'email' => $primaryEmail,
    //             ]);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert supplier: ' . $e->getMessage()];
    //             continue;
    //         }

    //         // Insert address into SupplierAddressModel
    //         try {
    //             SupplierAddressModel::create([
    //                 'company_id' => $company_id,
    //                 'supplier_id' => $register_supplier->supplier_id,
    //                 'address_line_1' => $addressData['address1'] ?? 'Default Address Line 1',
    //                 'address_line_2' => $addressData['address2'] ?? 'Default Address Line 2',
    //                 'city' => $addressData['city'] ?? 'Default City',
    //                 'pincode' => $addressData['pincode'] ?? '000000',
    //                 'state' => $record['state'] ?? 'Unknown State',
    //                 'country' => $record['country'] ?? 'India',
    //             ]);
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert address: ' . $e->getMessage()];
    //         }

    //         // Insert all contacts into SuppliersContactsModel
    //         $contacts = json_decode($record['contacts'], true) ?? [];
    //         foreach ($contacts as $contact) {
    //             try {
    //                 SuppliersContactsModel::create([
    //                     'supplier_id' => $register_supplier->supplier_id,
    //                     'company_id' => $company_id,
    //                     'name' => $contact['name'] ?? 'Unknown Contact',
    //                     'designation' => $contact['designation'] ?? 'Default Designation',
    //                     'mobile' => $contact['mobile'] ?? '0000000000',
    //                     'email' => filter_var($contact['email'] ?? '', FILTER_VALIDATE_EMAIL)
    //                         ? $contact['email']
    //                         : 'placeholder_' . now()->timestamp . '@example.com',
    //                 ]);
    //             } catch (\Exception $e) {
    //                 $errors[] = ['record' => $contact, 'error' => 'Failed to insert contact: ' . $e->getMessage()];
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Data import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

//     public function importSuppliersData()
// {
//     SuppliersModel::truncate();
//     SuppliersContactsModel::truncate();
//     SupplierAddressModel::truncate();

//     $url = 'https://expo.egsm.in/assets/custom/migrate/suppliers.php';

//     try {
//         $response = Http::get($url);
//     } catch (\Exception $e) {
//         return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
//     }

//     if ($response->failed()) {
//         return response()->json(['error' => 'Failed to fetch data.'], 500);
//     }

//     $data = $response->json('data');

//     if (empty($data)) {
//         return response()->json(['message' => 'No data found'], 404);
//     }

//     $successfulInserts = 0;
//     $errors = [];

//     $company_id = Auth::user()->company_id;

//     foreach ($data as $record) {
//         $existingSuppliers = SuppliersModel::where('name', $record['name'])->first();
//         if ($existingSuppliers) {
//             continue; // Skip if supplier already exists
//         }

//         $addressData = json_decode($record['address'], true) ?? [];

//         // Handle mobile and email for SuppliersModel
//         $primaryMobile = $record['mobile'] ?? '0000000000';
//         $primaryEmail = filter_var(trim($record['email'] ?? ''), FILTER_VALIDATE_EMAIL)
//             ? trim($record['email'])
//             : 'placeholder_' . now()->timestamp . '@example.com';

//         // Generate unique supplier ID
//         do {
//             $supplier_id = rand(1111111111, 9999999999);
//             $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
//         } while ($exists);

//         // Insert supplier record
//         try {
//             $register_supplier = SuppliersModel::create([
//                 'supplier_id' => $supplier_id,
//                 'company_id' => $company_id,
//                 'name' => $record['name'],
//                 'gstin' => $record['GSTIN'] ?? 'Random GSTIN' . now()->timestamp . '_' . Str::random(5),
//                 'contacts' => json_encode(['mobile' => $record['mobile'], 'email' => $record['email']]), // Store raw contact details as JSON
//                 'mobile' => $primaryMobile, // Only this mobile is stored
//                 'email' => $primaryEmail,  // Only this email is stored
//             ]);
//             $successfulInserts++;
//         } catch (\Exception $e) {
//             $errors[] = ['record' => $record, 'error' => 'Failed to insert supplier: ' . $e->getMessage()];
//             continue;
//         }

//         // Insert address into SupplierAddressModel
//         try {
//             SupplierAddressModel::create([
//                 'company_id' => $company_id,
//                 'supplier_id' => $register_supplier->supplier_id,
//                 'address_line_1' => $addressData['address1'] ?? 'Default Address Line 1',
//                 'address_line_2' => $addressData['address2'] ?? 'Default Address Line 2',
//                 'city' => $addressData['city'] ?? 'Default City',
//                 'pincode' => $addressData['pincode'] ?? '000000',
//                 'state' => $record['state'] ?? 'Unknown State',
//                 'country' => $record['country'] ?? 'India',
//             ]);
//         } catch (\Exception $e) {
//             $errors[] = ['record' => $record, 'error' => 'Failed to insert address: ' . $e->getMessage()];
//         }

//         // Handle contacts for SuppliersContactsModel only if contacts exist
//         if (!empty($record['contacts'])) {
//             $contacts = json_decode($record['contacts'], true);

//             // Ensure contacts fields are arrays
//             $names = $contacts['name'] ?? [];
//             $designations = $contacts['designation'] ?? [];
//             $mobiles = $contacts['mobile'] ?? [];
//             $emails = $contacts['email'] ?? [];

//             $maxCount = max(count($names), count($designations), count($mobiles), count($emails));

//             for ($i = 0; $i < $maxCount; $i++) {
//                 try {
//                     SuppliersContactsModel::create([
//                         'supplier_id' => $register_supplier->supplier_id,
//                         'company_id' => $company_id,
//                         'name' => $names[$i] ?? 'Unknown Contact',
//                         'designation' => $designations[$i] ?? 'Default Designation',
//                         'mobile' => $mobiles[$i] ?? '0000000000',
//                         'email' => filter_var($emails[$i] ?? '', FILTER_VALIDATE_EMAIL)
//                             ? $emails[$i]
//                             : 'placeholder_' . now()->timestamp . "@example.com",
//                     ]);
//                 } catch (\Exception $e) {
//                     $errors[] = [
//                         'record' => $contacts,
//                         'error' => 'Failed to insert contact: ' . $e->getMessage(),
//                     ];
//                 }
//             }
//         }
//     }

//     return response()->json([
//         'code' => 200,
//         'success' => true,
//         'message' => "Data import completed with $successfulInserts successful inserts.",
//         'errors' => $errors,
//     ], 200);
// }

    public function importSuppliersData()
    {
        SuppliersModel::truncate();
        SuppliersContactsModel::truncate();
        SupplierAddressModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/suppliers.php';

        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];
        $company_id = Auth::user()->company_id;

        foreach ($data as $record) {
            $existingSuppliers = SuppliersModel::where('name', $record['name'])->first();
            if ($existingSuppliers) {
                continue; // Skip if supplier already exists
            }

            $addressData = json_decode($record['address'], true) ?? [];

            // Process and normalize mobile numbers
            $rawMobileData = $record['mobile'] ?? '';
            $mobileList = $this->processMobileNumbers($rawMobileData); // Use helper function to process numbers
            $primaryMobile = $mobileList[0] ?? '0000000000'; // First number as the primary mobile

            $primaryEmail = filter_var(trim($record['email'] ?? ''), FILTER_VALIDATE_EMAIL)
                ? trim($record['email'])
                : 'placeholder_' . now()->timestamp . '@example.com';

            // Generate unique supplier ID
            do {
                $supplier_id = rand(1111111111, 9999999999);
                $exists = SuppliersModel::where('supplier_id', $supplier_id)->exists();
            } while ($exists);

            // Insert supplier record
            try {
                $register_supplier = SuppliersModel::create([
                    'supplier_id' => $supplier_id,
                    'company_id' => $company_id,
                    'name' => $record['name'],
                    'gstin' => $record['GSTIN'] ?? 'Random GSTIN' . now()->timestamp . '_' . Str::random(5),
                    'contacts' => json_encode(['mobile' => $rawMobileData, 'email' => $record['email']]), // Store raw contact details as JSON
                    'mobile' => $primaryMobile, // Store the first parsed mobile number
                    'email' => $primaryEmail,
                ]);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert supplier: ' . $e->getMessage()];
                continue;
            }

            // Insert address into SupplierAddressModel
            try {
                SupplierAddressModel::create([
                    'company_id' => $company_id,
                    'supplier_id' => $register_supplier->supplier_id,
                    'address_line_1' => $addressData['address1'] ?? 'Default Address Line 1',
                    'address_line_2' => $addressData['address2'] ?? 'Default Address Line 2',
                    'city' => $addressData['city'] ?? 'Default City',
                    'pincode' => $addressData['pincode'] ?? '000000',
                    'state' => $record['state'] ?? 'Unknown State',
                    'country' => $record['country'] ?? 'India',
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
                        'email' => $primaryEmail, // Use primary email for contacts
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


    use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
        $suppliersQuery->whereIn('supplier_id', $ids);
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

    // Format data for Excel
    $exportData = $suppliers->map(function ($supplier) {
        return [
            'Supplier ID' => $supplier->supplier_id,
            'Name' => $supplier->name,
            'GSTIN' => $supplier->gstin,
            'Contact Count' => $supplier->contacts->count(),
            'Contacts' => $supplier->contacts->map(fn($contact) => "{$contact->name} ({$contact->mobile})")->join(', '),
            'Address' => $supplier->addresses->map(fn($address) => "{$address->address_line_1}, {$address->city}, {$address->state}, {$address->pincode}, {$address->country}")->join('; '),
        ];
    })->toArray();

    // Generate Excel file using Laravel Excel
    $fileName = 'suppliers_export_' . now()->format('Ymd_His') . '.xlsx';

    return Excel::download(new class($exportData) implements FromCollection, WithHeadings {
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
                'Supplier ID',
                'Name',
                'GSTIN',
                'Contact Count',
                'Contacts',
                'Address',
            ];
        }
    }, $fileName);
}


}
