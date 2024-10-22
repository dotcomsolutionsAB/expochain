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
}
