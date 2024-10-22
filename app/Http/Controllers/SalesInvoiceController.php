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

    // View Sales Invoices
    public function view_sales_invoice()
    {
        $get_sales_invoices = SalesInvoiceModel::with(['products' => function ($query) {
            $query->select('sales_invoice_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
        }, 'addons' => function ($query) {
            $query->select('sales_invoice_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'sales_invoice_no', 'sales_invoice_date', 'sales_order_no', 'quotation_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status', 'commission', 'cash')
        ->get();

        return isset($get_sales_invoices) && $get_sales_invoices !== null
            ? response()->json(['Sales Invoices fetched successfully!', 'data' => $get_sales_invoices], 200)
            : response()->json(['Failed to fetch Sales Invoice data'], 404);
    }

    // Update Sales Invoice
    public function update_sales_invoice(Request $request)
    {
        $request->validate([
            'sales_invoice_id' => 'required|integer',
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
            'products.*.godown' => 'required|integer',
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
        ]);

        $salesInvoice = SalesInvoiceModel::where('id', $request->input('sales_invoice_id'))->first();

        $salesInvoiceUpdated = $salesInvoice->update([
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
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
            'commission' => $request->input('commission'),
            'cash' => $request->input('cash'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesInvoiceProductsModel::where('sales_invoice_id', $request->input('sales_invoice_id'))
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
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
                    'godown' => $productData['godown'],
                ]);
            } else {
                SalesInvoiceProductsModel::create([
                    'sales_invoice_id' => $request->input('sales_invoice_id'),
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
                    'godown' => $productData['godown'],
                ]);
            }
        }

        // Process addons
        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = SalesInvoiceAddonsModel::where('sales_invoice_id', $request->input('sales_invoice_id'))
                                                    ->where('name', $addonData['name'])
                                                    ->first();

            if ($existingAddon) {
                $existingAddon->update([
                    'amount' => $addonData['amount'],
                    'tax' => $addonData['tax'],
                    'hsn' => $addonData['hsn'],
                    'cgst' => $addonData['cgst'],
                    'sgst' => $addonData['sgst'],
                    'igst' => $addonData['igst'],
                ]);
            } else {
                SalesInvoiceAddonsModel::create([
                    'sales_invoice_id' => $request->input('sales_invoice_id'),
                    'name' => $addonData['name'],
                    'amount' => $addonData['amount'],
                    'tax' => $addonData['tax'],
                    'hsn' => $addonData['hsn'],
                    'cgst' => $addonData['cgst'],
                    'sgst' => $addonData['sgst'],
                    'igst' => $addonData['igst'],
                ]);
            }
        }

        // Delete products not included in the request
        $productsDeleted = SalesInvoiceProductsModel::where('sales_invoice_id', $request->input('sales_invoice_id'))
                                                    ->whereNotIn('product_id', $requestProductIDs)
                                                    ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesInvoiceAddonsModel::where('sales_invoice_id', $request->input('sales_invoice_id'))
                                                ->whereNotIn('name', $requestAddonIDs)
                                                ->delete();

        return ($salesInvoiceUpdated || $productsDeleted || $addonsDeleted)
            ? response()->json(['message' => 'Sales Invoice, products, and addons updated successfully!', 'data' => $salesInvoice], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // Delete Sales Invoice
    public function delete_sales_invoice($id)
    {
        $delete_sales_invoice = SalesInvoiceModel::where('id', $id)->delete();

        $delete_sales_invoice_products = SalesInvoiceProductsModel::where('sales_invoice_id', $id)->delete();
        $delete_sales_invoice_addons = SalesInvoiceAddonsModel::where('sales_invoice_id', $id)->delete();

        return $delete_sales_invoice && $delete_sales_invoice_products && $delete_sales_invoice_addons
            ? response()->json(['message' => 'Sales Invoice and associated products/addons deleted successfully!'], 200)
            : response()->json(['message' => 'Sales Invoice not found.'], 404);
    }
}
