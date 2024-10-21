<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\SalesOrderAddonsModel;

class SalesOrderController extends Controller
{
    //
    // create
    public function add_sales_order(Request $request)
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
            'sales_order_no' => 'required|integer',
            'sales_order_date' => 'required|date',
            'quotation_no' => 'required|integer',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
        ]);
    
    
        $register_sales_order = SalesOrderModel::create([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $request->input('sales_order_date'),
            'quotation_no' => $request->input('quotation_no'),
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
            SalesOrderProductsModel::create([
            'sales_order_id' => $register_sales_order['id'],
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
            SalesOrderAddonsModel::create([
            'sales_order_id' => $register_sales_order['id'],
            'name' => $addon['name'],
            'amount' => $addon['amount'],
            'tax' => $addon['tax'],
            'hsn' => $addon['hsn'],
            'cgst' => $addon['cgst'],
            'sgst' => $addon['sgst'],
            'igst' => $addon['igst'],
            ]);
        }

        unset($register_sales_order['id'], $register_sales_order['created_at'], $register_sales_order['updated_at']);
    
        return isset($register_sales_order) && $register_sales_order !== null
        ? response()->json(['Sales Order registered successfully!', 'data' => $register_sales_order], 201)
        : response()->json(['Failed to register Sales Order record'], 400);
    }
}
