<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseInvoiceProductsModel;

class PurchaseInvoiceController extends Controller
{
    //
    // create
    public function add_purchase_invoice(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'purchase_invoice_no' => 'required|string',
            'purchase_invoice_date' => 'required|date',
            'purchase_order_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.godown' => 'required|integer',
        ]);
    
    
        $register_purchase_invoice = PurchaseInvoiceModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'purchase_invoice_date' => $request->input('purchase_invoice_date'),
            'purchase_order_no' => $request->input('purchase_order_no'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            PurchaseInvoiceProductsModel::create([
                'purchase_invoice_number' => $register_purchase_invoice['id'],
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'brand' => $product['brand'],
                'quantity' => $product['quantity'],
                'brand' => $product['brand'],
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

        unset($register_purchase_invoice['id'], $register_purchase_invoice['created_at'], $register_purchase_invoice['updated_at']);
    
        return isset($register_purchase_invoice) && $register_purchase_invoice !== null
        ? response()->json(['Purchase Invoice registered successfully!', 'data' => $register_purchase_invoice], 201)
        : response()->json(['Failed to register Purchase Invoice record'], 400);
    }

    public function view_purchase_invoice()
    {
        $get_purchase_invoices = PurchaseInvoiceModel::with(['products' => function ($query) {
            $query->select('purchase_invoice_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
        }])
        ->select('id', 'supplier_id', 'name', 'purchase_invoice_no', 'purchase_invoice_date', 'purchase_order_no', 'cgst', 'sgst', 'igst', 'currency', 'template', 'status')
        ->get();

        return isset($get_purchase_invoices) && $get_purchase_invoices->isNotEmpty()
            ? response()->json(['Purchase Invoices fetched successfully!', 'data' => $get_purchase_invoices], 200)
            : response()->json(['Failed to fetch Purchase Invoice data'], 404);
    }

    public function edit_purchase_invoice(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'purchase_invoice_no' => 'required|string',
            'purchase_invoice_date' => 'required|date',
            'purchase_order_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.purchase_invoice_number' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.godown' => 'required|integer',
        ]);

        $purchaseInvoice = PurchaseInvoiceModel::where('id', $id)->first();

        $purchaseInvoiceUpdated = $purchaseInvoice->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'purchase_invoice_date' => $request->input('purchase_invoice_date'),
            'purchase_order_no' => $request->input('purchase_order_no'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = PurchaseInvoiceProductsModel::where('purchase_invoice_number', $productData['purchase_invoice_number'])
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
                    'godown' => $productData['godown'],
                ]);
            } else {
                // Add new product
                PurchaseInvoiceProductsModel::create([
                    'purchase_invoice_number' => $productData['purchase_invoice_number'],
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

        $productsDeleted = PurchaseInvoiceProductsModel::where('purchase_invoice_number', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        unset($purchaseInvoice['created_at'], $purchaseInvoice['updated_at']);

        return ($purchaseInvoiceUpdated || $productsDeleted)
            ? response()->json(['message' => 'Purchase Invoice and products updated successfully!', 'data' => $purchaseInvoice], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    public function delete_purchase_invoice($id)
    {
        $purchase_invoice = PurchaseInvoiceModel::find($id);

        if (!$purchase_invoice) {
            return response()->json(['message' => 'Purchase Invoice not found.'], 404);
        }

        // Delete related products first
        $products_deleted = PurchaseInvoiceProductsModel::where('purchase_invoice_number', $id)->delete();

        // Delete the purchase invoice
        $purchase_invoice_deleted = $purchase_invoice->delete();

        return ($products_deleted && $purchase_invoice_deleted)
            ? response()->json(['message' => 'Purchase Invoice and related products deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete Purchase Invoice.'], 400);
    }

}
