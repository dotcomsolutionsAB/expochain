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

    // View Sales Orders
    public function view_sales_order()
    {
        $get_sales_orders = SalesOrderModel::with(['products' => function ($query) {
            $query->select('sales_order_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }, 'addons' => function ($query) {
            $query->select('sales_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'sales_order_no', 'sales_order_date', 'quotation_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->get();

        return isset($get_sales_orders) && $get_sales_orders !== null
            ? response()->json(['Sales Orders fetched successfully!', 'data' => $get_sales_orders], 200)
            : response()->json(['Failed to fetch Sales Order data'], 404);
    }

    // Update Sales Order
    public function edit_sales_order(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'client_contact_id' => 'required|integer',
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
            'products' => 'required|array',
            'products.*.sales_order_id' => 'required|integer',
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
            'addons.*.sales_order_id' => 'required|integer',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
        ]);

        $salesOrder = SalesOrderModel::where('id', $id)->first();

        $salesOrderUpdated = $salesOrder->update([
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
        $requestProductIDs = [];

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesOrderProductsModel::where('sales_order_id', $productData['sales_order_id'])
                                                    ->where('product_id', $productData['product_id'])
                                                    ->toSql();
                                                    // dd($productData['product_id']);

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
                SalesOrderProductsModel::create([
                    'sales_order_id' => $productData['sales_order_id'],
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

        // Process addons
        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = SalesOrderAddonsModel::where('sales_order_id', $addonData['sales_order_id'])
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
                SalesOrderAddonsModel::create([
                    'sales_order_id' => $addonData['sales_order_id'],
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
        $productsDeleted = SalesOrderProductsModel::where('sales_order_id', $id)
                                                ->where('product_id', $requestProductIDs)
                                                ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesOrderAddonsModel::where('sales_order_id', $id)
                                            ->where('name', $requestAddonIDs)
                                            ->delete();

        return ($salesOrderUpdated || $productsDeleted || $addonsDeleted)
            ? response()->json(['message' => 'Sales Order, products, and addons updated successfully!', 'data' => $salesOrder], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // Delete Sales Order
    public function delete_sales_order($id)
    {
        $delete_sales_order = SalesOrderModel::where('id', $id)->delete();

        $delete_sales_order_products = SalesOrderProductsModel::where('sales_order_id', $id)->delete();
        $delete_sales_order_addons = SalesOrderAddonsModel::where('sales_order_id', $id)->delete();

        return $delete_sales_order && $delete_sales_order_products && $delete_sales_order_addons
            ? response()->json(['message' => 'Sales Order and associated products/addons deleted successfully!'], 200)
            : response()->json(['message' => 'Sales Order not found.'], 404);
    }
}
