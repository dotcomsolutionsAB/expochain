<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\SalesInvoiceModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\SalesInvoiceAddonsModel;
use App\Models\ClientsModel;
use App\Models\ClientsContactsModel;
use App\Models\ProductsModel;

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

        return isset($get_sales_invoices) && $get_sales_invoices->isNotEmpty()
            ? response()->json(['Sales Invoices fetched successfully!', 'data' => $get_sales_invoices], 200)
            : response()->json(['Failed to fetch Sales Invoice data'], 404);
    }

    // Update Sales Invoice
    public function edit_sales_invoice(Request $request, $id)
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
            'products' => 'required|array',
            'products.*.sales_invoice_id' => 'required|integer',
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
            'addons.*.sales_invoice_id' => 'required|integer',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
        ]);

        $salesInvoice = SalesInvoiceModel::where('id', $id)->first();

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

        // Handle Products
        $products = $request->input('products');
        $existingProductIDs = SalesInvoiceProductsModel::where('sales_invoice_id', $id)->pluck('product_id')->toArray();
        $requestProductIDs = [];

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesInvoiceProductsModel::where('sales_invoice_id', $productData['sales_invoice_id'])
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
                    'sales_invoice_id' => $productData['sales_invoice_id'],
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

            $existingAddon = SalesInvoiceAddonsModel::where('sales_invoice_id', $addonData['sales_invoice_id'])
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
                    'sales_invoice_id' => $addonData['sales_invoice_id'],
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
        $productsDeleted = SalesInvoiceProductsModel::where('sales_invoice_id', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesInvoiceAddonsModel::where('sales_invoice_id', $id)
                                                ->where('name', $requestAddonIDs)
                                                ->delete();

        unset($salesInvoice['created_at'], $salesInvoice['updated_at']);

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

    // public function importSalesInvoices()
    // {
    //     set_time_limit(300);

    //     // Clear the SalesInvoice and related tables
    //     SalesInvoiceModel::truncate();
    //     SalesInvoiceProductsModel::truncate();
    //     SalesInvoiceAddonsModel::truncate();

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/sells_invoice.php'; 

    //     // Fetch data from the external URL
    //     try {
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         // return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
    //         return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $successfulInserts = 0;
    //     $errors = [];

    //     foreach ($data as $record) {
    //         // Decode JSON fields for items, tax, and addons
    //         $itemsData = json_decode($record['items'] ?? '{}', true);
    //         $taxData = json_decode($record['tax'] ?? '{}', true);
    //         $addonsData = json_decode($record['addons'] ?? '{}', true);

    //         // Retrieve client and client contact IDs
    //         $client = ClientsModel::where('name', $record['client'])->first();

    //         if (!$client) {
    //             // If the client is not found, log an error or skip this record
    //             $errors[] = [
    //                 'record' => $record,
    //                 'error' => 'Client not found for the provided name: ' . $record['client']
    //             ];
    //             continue; // Skip to the next record in the loop
    //         }

    //         $clientContact = ClientsContactsModel::where('customer_id', $client->customer_id)->first();

    //         if (!$clientContact) {
    //             // If the client contact is not found, log an error or skip this record
    //             $errors[] = [
    //                 'record' => $record,
    //                 'error' => 'Client contact not found for customer ID: ' . $client->customer_id
    //             ];
    //             continue; // Skip to the next record in the loop
    //         }

    //         // Set up main sales invoice data with fallbacks
    //         $salesInvoiceData = [
    //             'client_id' => $client->id ?? null,
    //             'client_contact_id' => $clientContact->id ?? null,
    //             'name' => $record['client'] ?? 'Unnamed Client',
    //             'address_line_1' => $client->address_line_1 ?? 'Address Line 1',
    //             'address_line_2' => $client->address_line_2 ?? 'Address Line 2',
    //             'city' => $client->city ?? 'City Name',
    //             'pincode' => $client->pincode ?? '000000',
    //             'state' => $client->state ?? 'State Name',
    //             'country' => $client->country ?? 'India',
    //             'sales_invoice_no' => !empty($record['si_no']) ? (int) $record['si_no'] : 0,
    //             'sales_invoice_date' => $record['so_date'] ?? now(),
    //             'sales_order_no' => !empty($record['so_no']) ? (int) $record['so_no'] : 0,
    //             'quotation_no' => 0,
    //             'cgst' => $taxData['cgst'] ?? 0,
    //             'sgst' => $taxData['sgst'] ?? 0,
    //             'igst' => $taxData['igst'] ?? 0,
    //             'total' => $record['total'] ?? 0,
    //             'currency' => 'INR',
    //             'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
    //             'status' => $record['status'] ?? 1,
    //             'commission' => !empty($record['commission']) ? (float) $record['commission'] : 0,
    //             'cash' => !empty($record['cash']) ? (float) $record['cash'] : 0,
    //         ];

    //         // Validate main sales invoice data
    //         $validator = Validator::make($salesInvoiceData, [
    //             'client_id' => 'nullable|integer',
    //             'client_contact_id' => 'nullable|integer',
    //             'name' => 'required|string',
    //             'address_line_1' => 'required|string',
    //             'city' => 'required|string',
    //             'pincode' => 'required|string',
    //             'state' => 'required|string',
    //             'country' => 'required|string',
    //             'sales_invoice_no' => 'required|integer',
    //             'sales_invoice_date' => 'required|date',
    //             'sales_order_no' => 'required|integer',
    //             'quotation_no' => 'required|integer',
    //             'cgst' => 'required|numeric',
    //             'sgst' => 'required|numeric',
    //             'igst' => 'required|numeric',
    //             'total' => 'required|numeric',
    //             'currency' => 'required|string',
    //             'template' => 'required|string',
    //             'status' => 'required|integer',
    //             'commission' => 'required|numeric',
    //             'cash' => 'required|numeric',
    //         ]);

    //         if ($validator->fails()) {
    //             $errors[] = ['record' => $record, 'errors' => $validator->errors()];
    //             continue;
    //         }

    //         try {
    //             $salesInvoice = SalesInvoiceModel::create($salesInvoiceData);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert sales invoice: ' . $e->getMessage()];
    //             continue;
    //         }

    //         // Process items (products) associated with the sales invoice
    //         if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
    //             foreach ($itemsData['product'] as $index => $product) {
    //                 $productModel = ProductsModel::where('name', $product)->first();

    //                 // Check if the product exists
    //                 if (!$productModel) {
    //                     $errors[] = [
    //                         'record' => $itemsData,
    //                         'error' => "Product with name '{$product}' not found."
    //                     ];
    //                     continue; // Skip this product if not found
    //                 }

    //                 SalesInvoiceProductsModel::create([
    //                     'sales_invoice_id' => $salesInvoice->id,
    //                     'product_id' => $productModel->id,
    //                     'product_name' => $product,
    //                     'description' => $itemsData['desc'][$index] ?? '',
    //                     'brand' => $itemsData['group'][$index] ?? '',
    //                     'quantity' => $itemsData['quantity'][$index] ?? 0,
    //                     'unit' => $itemsData['unit'][$index] ?? '',
    //                     'price' => isset($itemsData['price'][$index]) && $itemsData['price'][$index] !== '' ? (float)$itemsData['price'][$index] : 0,
    //                     'discount' => (float)($itemsData['discount'][$index] ?? 0),
    //                     'hsn' => $itemsData['hsn'][$index] ?? '',
    //                     'tax' => isset($itemsData['tax'][$index]) && $itemsData['tax'][$index] !== '' ? (float)$itemsData['tax'][$index] : 0,
    //                     'cgst' => $itemsData['cgst'][$index] ?? 0,
    //                     'sgst' => $itemsData['sgst'][$index] ?? 0,
    //                     'igst' => $itemsData['igst'][$index] ?? 0,
    //                     'godown' => $itemsData['place'][$index] ?? '',
    //                 ]);
    //             }
    //         }

    //         // Process addons for the sales invoice
    //         if ($addonsData) {
    //             foreach ($addonsData as $name => $values) {
    //                 $totalAmount = (float)($values['igst'] ?? 0);

    //                 SalesInvoiceAddonsModel::create([
    //                     'sales_invoice_id' => $salesInvoice->id,
    //                     'name' => $name,
    //                     'amount' => $totalAmount,
    //                     'tax' => 18, // Default tax value
    //                     'hsn' => $values['hsn'] ?? '',
    //                     'cgst' => 0,
    //                     'sgst' => 0,
    //                     'igst' => (float)($values['igst'] ?? 0),
    //                 ]);
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'message' => "Sales invoices import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    public function importSalesInvoices()
    {
        set_time_limit(1200);

        // Clear existing data from SalesInvoice and related tables
        SalesInvoiceModel::truncate();
        SalesInvoiceProductsModel::truncate();
        SalesInvoiceAddonsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/sells_invoice.php';

        // Fetch data from the external URL
        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];

        // Chunk the data to process it in smaller batches
        $chunks = array_chunk($data, 500); // Process 100 records per chunk

        foreach ($chunks as $chunk) {
            foreach ($chunk as $record) {
                // Decode JSON fields for items, tax, and addons
                $itemsData = json_decode($record['items'] ?? '{}', true);
                $taxData = json_decode($record['tax'] ?? '{}', true);
                $addonsData = json_decode($record['addons'] ?? '{}', true);

                // Retrieve client and client contact IDs
                $client = ClientsModel::where('name', $record['client'])->first();
                if (!$client) {
                    $errors[] = [
                        'record' => $record,
                        'error' => 'Client not found for the provided name: ' . $record['client']
                    ];
                    continue;
                }

                $clientContact = ClientsContactsModel::where('customer_id', $client->customer_id)->first();
                if (!$clientContact) {
                    $errors[] = [
                        'record' => $record,
                        'error' => 'Client contact not found for customer ID: ' . $client->customer_id
                    ];
                    continue;
                }

                // Set up main sales invoice data
                $salesInvoiceData = [
                    'client_id' => $client->id ?? null,
                    'client_contact_id' => $clientContact->id ?? null,
                    'name' => $record['client'] ?? 'Unnamed Client',
                    'address_line_1' => $client->address_line_1 ?? 'Address Line 1',
                    'address_line_2' => $client->address_line_2 ?? 'Address Line 2',
                    'city' => $client->city ?? 'City Name',
                    'pincode' => $client->pincode ?? '000000',
                    'state' => $client->state ?? 'State Name',
                    'country' => $client->country ?? 'India',
                    'sales_invoice_no' => !empty($record['si_no']) ? (int) $record['si_no'] : 0,
                    'sales_invoice_date' => $record['so_date'] ?? now(),
                    'sales_order_no' => !empty($record['so_no']) ? (int) $record['so_no'] : 0,
                    'quotation_no' => 0,
                    'cgst' => $taxData['cgst'] ?? 0,
                    'sgst' => $taxData['sgst'] ?? 0,
                    'igst' => $taxData['igst'] ?? 0,
                    'total' => $record['total'] ?? 0,
                    'currency' => 'INR',
                    'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                    'status' => $record['status'] ?? 1,
                    'commission' => !empty($record['commission']) ? (float) $record['commission'] : 0,
                    'cash' => !empty($record['cash']) ? (float) $record['cash'] : 0,
                ];

                // Validate sales invoice data
                $validator = Validator::make($salesInvoiceData, [
                    'client_id' => 'nullable|integer',
                    'client_contact_id' => 'nullable|integer',
                    'name' => 'required|string',
                    'address_line_1' => 'required|string',
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
                    'template' => 'required|string',
                    'status' => 'required|integer',
                    'commission' => 'required|numeric',
                    'cash' => 'required|numeric',
                ]);

                if ($validator->fails()) {
                    $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                    continue;
                }

                // Create sales invoice record
                try {
                    $salesInvoice = SalesInvoiceModel::create($salesInvoiceData);
                    $successfulInserts++;
                } catch (\Exception $e) {
                    $errors[] = ['record' => $record, 'error' => 'Failed to insert sales invoice: ' . $e->getMessage()];
                    continue;
                }

                // Process items (products) associated with the sales invoice
                if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
                    foreach ($itemsData['product'] as $index => $product) {
                        $productModel = ProductsModel::where('name', $product)->first();

                        if (!$productModel) {
                            $errors[] = [
                                'record' => $itemsData,
                                'error' => "Product with name '{$product}' not found."
                            ];
                            continue;
                        }

                        SalesInvoiceProductsModel::create([
                            'sales_invoice_id' => $salesInvoice->id,
                            'product_id' => $productModel->id,
                            'product_name' => $product,
                            'description' => $itemsData['desc'][$index] ?? '',
                            'brand' => $itemsData['group'][$index] ?? '',
                            'quantity' => $itemsData['quantity'][$index] ?? 0,
                            'unit' => $itemsData['unit'][$index] ?? '',
                            'price' => isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0,
                            'discount' => (float) ($itemsData['discount'][$index] ?? 0),
                            'hsn' => $itemsData['hsn'][$index] ?? '',
                            'tax' => isset($itemsData['tax'][$index]) ? (float) $itemsData['tax'][$index] : 0,
                            'cgst' => $itemsData['cgst'][$index] ?? 0,
                            'sgst' => $itemsData['sgst'][$index] ?? 0,
                            'igst' => $itemsData['igst'][$index] ?? 0,
                            'godown' => $itemsData['place'][$index] ?? '',
                        ]);
                    }
                }

                // Process addons for the sales invoice
                if ($addonsData) {
                    foreach ($addonsData as $name => $values) {
                        $totalAmount = (float) ($values['igst'] ?? 0);

                        SalesInvoiceAddonsModel::create([
                            'sales_invoice_id' => $salesInvoice->id,
                            'name' => $name,
                            'amount' => $totalAmount,
                            'tax' => 18, // Default tax value
                            'hsn' => $values['hsn'] ?? '',
                            'cgst' => 0,
                            'sgst' => 0,
                            'igst' => (float) ($values['igst'] ?? 0),
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'message' => "Sales invoices import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
