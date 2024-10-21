<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuotationsModel;
use App\Models\QuotationProductsModel;
use App\Models\QuotationAddonsModel;
use App\Models\QuotationTermsModel;

class QuotationsController extends Controller
{
    //

    // create
    public function add_quotations(Request $request)
    {
        $request->validate([
            'client_id' => 'required',
            'client_contact_id' => 'required',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required',
            'state' => 'required|string',
            'country' => 'required|string',
            'quotation_date' => 'required',
            'enquiry_no' => 'required',
            'enquiry_date' => 'required',
            'sales_person' => 'required|string',
            'sales_contact' => 'required|string',
            'sales_email' => 'required|string',
            'discount' => 'required',
            'cgst' => 'required',
            'sgst' => 'required',
            'igst' => 'required',
            'total' => 'required',
            'currency' => 'required',
            'template' => 'required',
        ]);
    
        $quotation_no = rand(1111111111,9999999999);
    
        $register_quotations = QuotationsModel::create([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'quotation_no' => $quotation_no,
            'quotation_date' => $request->input('quotation_date'),
            'enquiry_no' => $request->input('enquiry_no'),
            'enquiry_date' => $request->input('enquiry_date'),
            'sales_person' => $request->input('sales_person'),
            'sales_contact' => $request->input('sales_contact'),
            'sales_email' => $request->input('sales_email'),
            'discount' => $request->input('discount'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
        ]);
        
        $products = $request->input('products');
    
        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            QuotationProductsModel::create([
            'quotation_id' => $register_quotations['id'],
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

        $addons = $request->input('addons');
    
        // Iterate over the addons array and insert each contact
        foreach ($addons as $addon) 
        {
            QuotationAddonsModel::create([
            'quotation_id' => $register_quotations['id'],
            'name' => $addon['name'],
            'amount' => $addon['amount'],
            'tax' => $addon['tax'],
            'hsn' => $addon['hsn'],
            'cgst' => $addon['cgst'],
            'sgst' => $addon['sgst'],
            'igst' => $addon['igst'],
            ]);
        }

        $terms = $request->input('terms');
    
        // Iterate over the terms array and insert each contact
        foreach ($terms as $term) 
        {
            QuotationTermsModel::create([
            'quotation_id' => $register_quotations['id'],
            'name' => $term['name'],
            'value' => $term['value'],
            ]);
        }

        unset($register_quotations['id'], $register_quotations['created_at'], $register_quotations['updated_at']);
    
        return isset($register_quotations) && $register_quotations !== null
        ? response()->json(['Quotatins registered successfully!', 'data' => $register_quotations], 201)
        : response()->json(['Failed to register quotations record'], 400);
    }
}
