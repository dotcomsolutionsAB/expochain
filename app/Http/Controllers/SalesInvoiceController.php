<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesInvoiceModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\SalesInvoiceAddonsModel;

class SalesInvoiceController extends Controller
{
    //
    // create
    public function add_sales_invoice(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'client_contact_id' => 'required|integer',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'sales_invoice_no' => 'required|integer',
            'sales_invoice_date' => 'required|date',
            'sales_order_no' => 'required|integer',
            'quotation_no' => 'required|integer',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'commission' => 'required|numeric',
            'cash' => 'required|numeric',
        ]);


        $register_sales_invoice = SalesInvoiceModel::create([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'sales_invoice_date' => $request->input('sales_invoice_date'),
            'sales_order_no' => $request->input('sales_order_no'),
            'quotation_no' => $request->input('quotation_no'),
            'cgst' => $request->input('cgst', 0),
            'sgst' => $request->input('sgst', 0),
            'igst' => $request->input('igst', 0),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
            'commission' => $request->input('commission'),
            'cash' => $request->input('cash'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            SalesInvoiceProductsModel::create([
            'sales_invoice_id' => $register_sales_invoice['id'],
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
            'godown' => $product['godown'],
            ]);
        }

        $addons = $request->input('addons');

        // Iterate over the addons array and insert each contact
        foreach ($addons as $addon) 
        {
            SalesInvoiceAddonsModel::create([
            'sales_invoice_id' => $register_sales_invoice['id'],
            'name' => $addon['name'],
            'amount' => $addon['amount'],
            'tax' => $addon['tax'],
            'hsn' => $addon['hsn'],
            'cgst' => $addon['cgst'],
            'sgst' => $addon['sgst'],
            'igst' => $addon['igst'],
            ]);
        }

        unset($register_sales_invoice['id'], $register_sales_invoice['created_at'], $register_sales_invoice['updated_at']);

        return isset($register_sales_invoice) && $register_sales_invoice !== null
        ? response()->json(['Sales Order Invoice registered successfully!', 'data' => $register_sales_invoice], 201)
        : response()->json(['Failed to register Sales Order Invoice record'], 400);
    }
}
