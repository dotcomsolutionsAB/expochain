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
}
