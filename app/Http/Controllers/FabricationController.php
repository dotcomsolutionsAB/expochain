<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FabricationModel;

class FabricationController extends Controller
{
    //
    //create
    public function add_fabrication(Request $request)
    {
        $request->validate([
            'fabrication_date' => 'required|date',
            'product_id' => 'required|integer',
            'product_name' => 'required|string',
            'type' => 'required|in:wastage,sample',
            'quantity' => 'required|integer',
            'godown' => 'required|integer',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'log_user' => 'required|string'
        ]);

        $register_fabrication = FabricationModel::create([
            'fabrication_date' => $request->input('fabrication_date'),
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'type' => $request->input('type'),
            'quantity' => $request->input('quantity'),
            'godown' => $request->input('godown'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'log_user' => $request->input('log_user')
        ]);
        
        unset($register_fabrication['id'], $register_fabrication['created_at'], $register_fabrication['updated_at']);

        return isset($register_fabrication) && $register_fabrication !== null
        ? response()->json(['Fabrication registered successfully!', 'data' => $register_fabrication], 201)
        : response()->json(['Failed to register Fabrication record'], 400);
    }

    public function view_fabrication()
    {        
        $get_fabrication = FabricationModel::select('id', 'fabrication_date','product_id', 'product_name','type', 'quantity', 'godown', 'rate', 'amount', 'description', 'log_user')->get();
        

        return isset($get_fabrication) && $get_fabrication->isNotEmpty()
        ? response()->json(['Fetch data successfully!', 'data' => $get_fabrication], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_fabrication(Request $request, $id)
    {
        $request->validate([
            'fabrication_date' => 'required|date',
            'product_id' => 'required|integer',
            'product_name' => 'required|string',
            'type' => 'required|in:wastage,sample',
            'quantity' => 'required|integer',
            'godown' => 'required|integer',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'log_user' => 'required|string'
        ]);

        $update_fabrication = FabricationModel::where('id', $id)
        ->update([
            'fabrication_date' => $request->input('fabrication_date'),
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'type' => $request->input('type'),
            'quantity' => $request->input('quantity'),
            'godown' => $request->input('godown'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'log_user' => $request->input('log_user')
        ]);
        
        return $update_fabrication
        ? response()->json(['Fabrication updated successfully!', 'data' => $update_fabrication], 201)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_fabrication($id)
    {
        // Delete the fabrication
        $delete_fabrication = FabricationModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_fabrication
        ? response()->json(['message' => 'Delete Fabrication successfully!'], 204)
        : response()->json(['message' => 'Sorry, Fabrication not found'], 400);
    }
}
