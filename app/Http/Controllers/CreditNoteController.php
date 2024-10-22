<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CreditNoteModel;
use App\Models\CreditNoteProductsModel;

class CreditNoteController extends Controller
{
    //
    // create
    public function add_credit_note(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'credit_note_no' => 'required|string',
            'credit_note_date' => 'required|date',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|integer',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric'
        ]);
    
    
        $register_credit_note = CreditNoteModel::create([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'credit_note_no' => $request->input('credit_note_no'),
            'credit_note_date' => $request->input('credit_note_date'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            CreditNoteProductsModel::create([
                'credit_note_id' => $register_credit_note['id'],
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'brand' => $product['brand'],
                'quantity' => $product['quantity'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'discount' => $product['discount'],
                'hsn' => $product['hsn'],
                'tax' => $product['tax'],
                'cgst' => $product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
            ]);
        }

        unset($register_credit_note['id'], $register_credit_note['created_at'], $register_credit_note['updated_at']);
    
        return isset($register_credit_note) && $register_credit_note !== null
        ? response()->json(['Credit Note registered successfully!', 'data' => $register_credit_note], 201)
        : response()->json(['Failed to register Credit Note record'], 400);
    }

    // view
    public function view_credit_note()
    {
        $get_credit_notes = CreditNoteModel::with(['products' => function ($query) {
            $query->select('credit_note_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'client_id', 'name', 'credit_note_no', 'credit_note_date', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->get();

        return isset($get_credit_notes) && $get_credit_notes !== null
            ? response()->json(['Credit Notes fetched successfully!', 'data' => $get_credit_notes], 200)
            : response()->json(['Failed to fetch Credit Note data'], 404);
    }

    // update
    public function update_credit_note(Request $request)
    {
        $request->validate([
            'credit_note_id' => 'required|integer',
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'credit_note_no' => 'required|string',
            'credit_note_date' => 'required|date',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|integer',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
        ]);

        $creditNote = CreditNoteModel::where('id', $request->input('credit_note_id'))->first();

        $creditNoteUpdated = $creditNote->update([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'credit_note_no' => $request->input('credit_note_no'),
            'credit_note_date' => $request->input('credit_note_date'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = CreditNoteProductsModel::where('credit_note_id', $request->input('credit_note_id'))
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
                // Update existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'brand' => $productData['brand'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                ]);
            } else {
                // Create new product
                CreditNoteProductsModel::create([
                    'credit_note_id' => $request->input('credit_note_id'),
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'brand' => $productData['brand'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                ]);
            }
        }

        // Delete products not included in the request
        $productsDeleted = CreditNoteProductsModel::where('credit_note_id', $request->input('credit_note_id'))
                                                ->whereNotIn('product_id', $requestProductIDs)
                                                ->delete();

        return ($creditNoteUpdated || $productsDeleted)
            ? response()->json(['message' => 'Credit Note and products updated successfully!', 'data' => $creditNote], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // Delete Credit Note
    public function delete_credit_note($id)
    {
        $delete_credit_note = CreditNoteModel::where('id', $id)->delete();

        $delete_credit_note_products = CreditNoteProductsModel::where('credit_note_id', $id)->delete();

        return $delete_credit_note && $delete_credit_note_products
            ? response()->json(['message' => 'Credit Note and associated products deleted successfully!'], 200)
            : response()->json(['message' => 'Credit Note not found.'], 404);
    }
}
