<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SuppliersModel;
use App\Models\SuppliersContactsModel;

class SuppliersController extends Controller
{
    //
    // suppliers table
    //create
    public function add_suppliers(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:t_suppliers,name',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'gstin' => 'required|string|unique:t_suppliers,gstin',
            'contacts' => 'required',
        ]);

        $supplier_id = rand(1111111111,9999999999);

        $contacts = $request->input('contacts');

        // Iterate over the contacts array and insert each contact
        foreach ($contacts as $contact) {
                SuppliersContactsModel::create([
                'supplier_id' => $supplier_id,
                'name' => $contact['name'],
                'designation' => $contact['designation'],
                'mobile' => $contact['mobile'],
                'email' => $contact['email'],
            ]);
        }

        $register_suppliers = SuppliersModel::create([
            'supplier_id' => $supplier_id,
            'name' => $request->input('name'),
            'address_line_1	' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
        ]);
        
        unset($register_suppliers['id'], $register_suppliers['created_at'], $register_suppliers['updated_at']);

        return isset($register_suppliers) && $register_suppliers !== null
        ? response()->json(['Suppliers registered successfully!', 'data' => $register_suppliers], 201)
        : response()->json(['Failed to register Suppliers record'], 400);
    }

    // view
    public function view_suppliers()
    {        
        $get_suppliers = SuppliersModel::with(['contact' => function ($query)
        {
            $query->select('supplier_id','name','designation','mobile','email');
        }])
        ->select('supplier_id','name','address_line_1','address_line_2', 'city', 'pincode', 'state', 'country','gstin')->get();
        

        return isset($get_suppliers) && $get_suppliers !== null
        ? response()->json(['Fetch record successfully!', 'data' => $get_suppliers], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function update_suppliers(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'supplier_id' => 'required',
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
        $suppliers = SuppliersModel::where('supplier_id', $request->input('supplier_id'))->first();

        // Update the client information
        $suppliersUpdated = $suppliers->update([
            'name' => $request->input('name'),
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
            $contact = SuppliersContactsModel::where('supplier_id', $contactData['supplier_id'])
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
                $newContact = SuppliersContactsModel::create([
                    'supplier_id' => $client->supplier_id,
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
        $contactsDeleted = SuppliersContactsModel::where('supplier_id', $suppliers->supplier_id)
        ->whereNotIn('name', $requestContactNames)  // Delete if name is not in the request
        ->delete();

        unset($suppliers['id'], $suppliers['created_at'], $suppliers['updated_at']);

        return ($suppliersUpdated || $contactsUpdated || $contactsDeleted)
        ? response()->json(['message' => 'Client and contacts updated successfully!', 'client' => $suppliers], 200)
        : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_supplier($id)
    {
        // Try to find the client by the given ID
        $get_supplier_id = SuppliersModel::select('supplier_id')
                                        ->where('id', $id)
                                        ->first();
        
        // Check if the client exists

        if ($get_supplier_id) 
        {
            // Delete the client
            $delete_supplier = SuppliersModel::where('id', $id)->delete();

            // Delete associated contacts by customer_id
            $delete_contact_records = SuppliersContactsModel::where('supplier_id', $get_supplier_id->supplier_id)->delete();

            // Return success response if deletion was successful
            return $delete_supplier && $delete_contact_records
            ? response()->json(['message' => 'Supplier and associated contacts deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete supplier or contacts.'], 400);

        } 
        else 
        {
            // Return error response if supplier not found
            return response()->json(['message' => 'Supplier not found.'], 404);
        }
    }
}
