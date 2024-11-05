<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\QuotationsModel;
use App\Models\QuotationProductsModel;
use App\Models\QuotationAddonsModel;
use App\Models\QuotationTermsModel;
use App\Models\ClientsModel;
use App\Models\ClientsContactsModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
        ? response()->json(['Quotations registered successfully!', 'data' => $register_quotations], 201)
        : response()->json(['Failed to register quotations record'], 400);
    }

    // View Quotations
    public function view_quotations()
    {
        $get_quotations = QuotationsModel::with(['products' => function ($query) {
            $query->select('quotation_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }, 'addons' => function ($query) {
            $query->select('quotation_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
        }, 'terms' => function ($query) {
            $query->select('quotation_id', 'name', 'value');
        }])
        ->select('id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'quotation_no', 'quotation_date', 'enquiry_no', 'enquiry_date', 'sales_person', 'sales_contact', 'sales_email', 'discount', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template')
        ->get();

        return isset($get_quotations) && $get_quotations !== null
            ? response()->json(['Quotations fetched successfully!', 'data' => $get_quotations], 200)
            : response()->json(['Failed to fetch quotations data'], 404);
    }

    // Update Quotations
    public function update_quotations(Request $request)
    {
        $request->validate([
            'quotation_id' => 'required|integer',
            'client_id' => 'required|integer',
            'client_contact_id' => 'required|integer',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'quotation_date' => 'required|date',
            'enquiry_no' => 'required|string',
            'enquiry_date' => 'required|date',
            'sales_person' => 'required|string',
            'sales_contact' => 'required|string',
            'sales_email' => 'required|string',
            'discount' => 'required|numeric',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
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
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string',
            'terms.*.value' => 'required|string',
        ]);

        $quotation = QuotationsModel::where('id', $request->input('quotation_id'))->first();

        $quotationUpdated = $quotation->update([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
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
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = QuotationProductsModel::where('quotation_id', $request->input('quotation_id'))
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
                ]);
            } else {
                QuotationProductsModel::create([
                    'quotation_id' => $request->input('quotation_id'),
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

        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = QuotationAddonsModel::where('quotation_id', $request->input('quotation_id'))
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
                QuotationAddonsModel::create([
                    'quotation_id' => $request->input('quotation_id'),
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

        $terms = $request->input('terms');
        $requestTermIDs = [];

        foreach ($terms as $termData) {
            $requestTermIDs[] = $termData['name'];

            $existingTerm = QuotationTermsModel::where('quotation_id', $request->input('quotation_id'))
                                            ->where('name', $termData['name'])
                                            ->first();

            if ($existingTerm) {
                $existingTerm->update([
                    'value' => $termData['value'],
                ]);
            } else {
                QuotationTermsModel::create([
                    'quotation_id' => $request->input('quotation_id'),
                    'name' => $termData['name'],
                    'value' => $termData['value'],
                ]);
            }
        }

        // Delete products, addons, and terms not included in the request
        QuotationProductsModel::where('quotation_id', $request->input('quotation_id'))
                            ->whereNotIn('product_id', $requestProductIDs)
                            ->delete();

        QuotationAddonsModel::where('quotation_id', $request->input('quotation_id'))
                            ->whereNotIn('name', $requestAddonIDs)
                            ->delete();

        QuotationTermsModel::where('quotation_id', $request->input('quotation_id'))
                        ->whereNotIn('name', $requestTermIDs)
                        ->delete();

        return ($quotationUpdated)
            ? response()->json(['message' => 'Quotation, products, addons, and terms updated successfully!', 'data' => $quotation], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // Delete Quotations
    public function delete_quotations($id)
    {
        $delete_quotation = QuotationsModel::where('id', $id)->delete();
        $delete_quotation_products = QuotationProductsModel::where('quotation_id', $id)->delete();
        $delete_quotation_addons = QuotationAddonsModel::where('quotation_id', $id)->delete();
        $delete_quotation_terms = QuotationTermsModel::where('quotation_id', $id)->delete();

        return $delete_quotation && $delete_quotation_products && $delete_quotation_addons && $delete_quotation_terms
            ? response()->json(['message' => 'Quotation and associated data deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete quotation or associated data.'], 400);
    }

    // migrate data
    public function importQuotations()
    {
        QuotationsModel::truncate();  
        
        QuotationProductsModel::truncate();  

        QuotationAddonsModel::truncate();  
        
        QuotationTermsModel::truncate();  

        $url = 'https://expo.egsm.in/assets/custom/migrate/quotation.php';

        // Fetch data from the external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
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

        foreach ($data as $record) {
            // Check if 'addons' and other JSON fields decode correctly as arrays
            $enquiryData = json_decode($record['enq_no_date'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);
            $termsData = json_decode($record['Terms'] ?? '{}', true);
            $itemsData = json_decode($record['items'] ?? '{}', true);
            // Parse 'tax' field to get cgst, sgst, and igst
            $taxData = json_decode($record['tax'], true);

            if (!is_array($addonsData) || !is_array($enquiryData) || !is_array($termsData) || !is_array($itemsData)) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in one of the fields.'];
                continue;
            }

            // Generate dummy sales data and fallback for missing fields
            $client = ClientsModel::where('name', $record['Client'])->first();
            $client_contact_id = ClientsContactsModel::select('id')->where('customer_id', $client->customer_id)->first();
            $salesPerson = 'Sales Person ' . Str::random(5);
            $salesContact = 'Contact ' . rand(100000, 999999);
            $salesEmail = 'placeholder_' . now()->timestamp . '@example.com';

            // Set up main quotation data with fallbacks
            $quotationData = [
                'client_id' => $client->id ?? null,
                'client_contact_id' => $client_contact_id->id ?? null,
                'name' => $record['Client'] ?? 'Unnamed Client',
                'address_line_1' => $client->address_line_1 ?? 'Random Address1_' . now()->timestamp,
                'address_line_2' => $client->address_line_2 ?? 'Random Address2_' . now()->timestamp,
                'city' => $client->city ?? 'Random City',
                'pincode' => $client->pincode ?? '000000',
                'state' => $client->state ?? 'Unknown State',
                'country' => $client->country ?? 'India',
                'quotation_no' => $record['quotation_no'],
                'quotation_date' => $record['quotation_date'],
                'enquiry_no' => $enquiryData['enquiry_no'] ?? 'No Enquiry',
                'enquiry_date' => $enquiryData['enquiry_date'] ?? '1970-01-01',
                'sales_person' => $salesPerson,
                'sales_contact' => $salesContact,
                'sales_email' => $salesEmail,
                'discount' => (!empty($record['discount']) || $record['discount'] === "0") ? $record['discount'] : 0,
                'cgst' => $taxData['cgst'] ?? 0,
                'sgst' => $taxData['sgst'] ?? 0,
                'igst' => $taxData['igst'] ?? 0,
                'total' => $record['total'] ?? 0,
                'currency' => $record['currency'] ?? 'INR',
                'template' => json_decode($record['template'], true)['id'] ?? '0',
            ];

            // Validate main quotation data
            $validator = Validator::make($quotationData, [
                'client_id' => 'nullable|integer',
                'name' => 'required|string',
                'address_line_1' => 'required|string',
                'city' => 'required|string',
                'pincode' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'quotation_no' => 'required',
                'quotation_date' => 'required|date',
                'enquiry_no' => 'required',
                'enquiry_date' => 'required|date',
                'sales_person' => 'required|string',
                'sales_contact' => 'required|string',
                'sales_email' => 'required|string|email',
                'discount' => 'required|numeric',
                'cgst' => 'required|numeric',
                'sgst' => 'required|numeric',
                'igst' => 'required|numeric',
                'total' => 'required|numeric',
                'currency' => 'required|string',
                'template' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                $quotation = QuotationsModel::create($quotationData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert quotation: ' . $e->getMessage()];
                continue;
            }

            // Initialize the product ID counter
            $productIdCounter = 1;

            // Proceed with items, addons, and terms if main quotation save succeeds
            if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {
                    QuotationProductsModel::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $productIdCounter,  // Set the product ID
                        'product_name' => $itemsData['product'][$index] ?? 'Unnamed Product',
                        'description' => $itemsData['desc'][$index] ?? 'No Description',
                        'brand' => $itemsData['brand'][$index] ?? 'null',
                        'quantity' => $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => $itemsData['price'][$index] ?? 0,
                        'discount' => (float)($itemsData['discount'][$index] ?? 0), // Ensures discount is numeric
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => $itemsData['tax'][$index] ?? 0,
                        'cgst' => $itemsData['cgst'][$index] ?? 0,
                        'sgst' => $itemsData['sgst'][$index] ?? 0,
                        'igst' => array_key_exists('igst', $itemsData) && isset($itemsData['igst'][$index])
                        ? $itemsData['igst'][$index]
                        : 0,  // Set igst to 0 if it's missing
                    ]);

                    // Increment the product ID counter for the next product
                    $productIdCounter++;

                }
            }

            if ($addonsData) {
                foreach ($addonsData as $name => $values) {
                    if ($name === 'roundoff') continue; // Skip 'roundoff'

                    // Calculate the total for amount as the sum of cgst, sgst, and igst
                    $totalAmount = (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0);

                    QuotationAddonsModel::create([
                        'quotation_id' => $quotation->id,
                        'name' => $name,
                        'amount' => $totalAmount,
                        'tax' => 18,
                        'hsn' => array_key_exists('hsn', $values) && isset($values['hsn'][$index])
                        ? $itemsData['hsn'][$index]
                        : 0,  // Set igst to 0 if it's missing
                        'cgst' => (float)($values['cgst'] ?? 0),
                        'sgst' => (float)($values['sgst'] ?? 0),
                        'igst' => (float)($values['igst'] ?? 0),
                    ]);
                }
            }

            if ($termsData) {
                foreach ($termsData as $name => $value) {
                    QuotationTermsModel::create([
                        'quotation_id' => $quotation->id,
                        'name' => $name,
                        'value' => $value,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => "Quotations import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
}
