<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\clientsModel;
use App\Models\ClientsContactsModel;

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

        $customer_id = rand(1111111111,9999999999);

        $contacts = $request->input('contacts');

        // Iterate over the contacts array and insert each contact
        foreach ($contacts as $contact) {
            ClientsContactsModel::create([
            'customer_id' => $customer_id,
            'name' => $contact['name'],
            'designation' => $contact['designation'],
            'mobile' => $contact['mobile'],
            'email' => $contact['email'],
            ]);
        }

        $register_clients = clientsModel::create([
            'name' => $request->input('name'),
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
        ->select('name','customer_id','type','category', 'division', 'plant', 'address_line_1', 'address_line_2', 'city','pincode','state','country', 'gstin')->get();
        

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
        $get_client_id = ClientsModel::select('customer_id')
                                     ->where('id', $id)
                                     ->first();
        
        // Check if the client exists

        if ($get_client_id) 
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
}
