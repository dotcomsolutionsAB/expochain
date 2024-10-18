<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\clientsModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;
use App\Models\FinancialYearModel;
use App\Models\PdfSetupModel;


class MastersController extends Controller
{
    // clients table
    // create
    public function add_clients(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'customer_id' => 'required',
            'type' => 'required|string',
            'category' => 'required|string',
            'division' => 'required|string',
            'plant' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'gstin' => 'required|string',
        ]);

        $register_clients = clientsModel::create([
            'name' => $request->input('name'),
            'customer_id' => $request->input('customer_id'),
            'type' => $request->input('type'),
            'category' => $request->input('category'),
            'division' => $request->input('division'),
            'plant' => $request->input('plant'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
        ]);
        
        unset($register_clients['id'], $register_clients['created_at'], $register_clients['updated_at']);

        return isset($register_clients) && $register_clients !== null
        ? response()->json(['Client registered successfully!', 'data' => $register_clients], 201)
        : response()->json(['Failed to register client record'], 400);
    }

        // view


    // clients table
    //create
    public function add_suppliers(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required',
            'name' => 'required',
            'address_line_1	' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'gstin' => 'required|string',
        ]);

        $register_suppliers = SuppliersModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'address_line_1	' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
        ]);
        
        unset($register_suppliers['id'], $register_suppliers['created_at'], $register_suppliers['updated_at']);

        return isset($register_suppliers) && $register_suppliers !== null
        ? response()->json(['Suppliers registered successfully!', 'data' => $register_suppliers], 201)
        : response()->json(['Failed to register Suppliers record'], 400);
    }

    // products table
    //create
    public function add_products(Request $request)
    {
        $request->validate([
            'serial_number' => 'required',
            'name' => 'required',
            'alias	' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'brand' => 'required|string',
            'category' => 'required|string',
            'sub_category' => 'required|string',
            'cost_price' => 'required',
            'sale_price' => 'required',
            'unit' => 'required',
            'hsn' => 'required|string',
            'tax' => 'required',
        ]);

        $register_products = ProductsModel::create([
            'serial_number' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'alias	' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'sub_category' => $request->input('sub_category'),
            'cost_price' => $request->input('cost_price'),
            'sale_price' => $request->input('sale_price'),
            'unit' => $request->input('unit'),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),

        ]);
        
        unset($register_products['id'], $register_products['created_at'], $register_products['updated_at']);

        return isset($register_products) && $register_products !== null
        ? response()->json(['Products registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Products record'], 400);
    }

    // finacial year table
    //create
    public function add_f_year(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'name' => 'required',
            'alias	' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'brand' => 'required|string',
            'category' => 'required|string',
            'sub_category' => 'required|string',
            'cost_price' => 'required',
            'sale_price' => 'required',
            'unit' => 'required',
            'hsn' => 'required|string',
            'tax' => 'required',
        ]);

        $register_products = ProductsModel::create([
            'serial_number' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'alias	' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'sub_category' => $request->input('sub_category'),
            'cost_price' => $request->input('cost_price'),
            'sale_price' => $request->input('sale_price'),
            'unit' => $request->input('unit'),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),

        ]);
        
        unset($register_products['id'], $register_products['created_at'], $register_products['updated_at']);

        return isset($register_products) && $register_products !== null
        ? response()->json(['Products registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Products record'], 400);
    }

    // pdf setup table
    //create
    public function add_pdf_setup(Request $request)
    {
        $request->validate([
            'serial_number' => 'required',
            'name' => 'required',
            'alias	' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'brand' => 'required|string',
            'category' => 'required|string',
            'sub_category' => 'required|string',
            'cost_price' => 'required',
            'sale_price' => 'required',
            'unit' => 'required',
            'hsn' => 'required|string',
            'tax' => 'required',
        ]);

        $register_products = ProductsModel::create([
            'serial_number' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'alias	' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'sub_category' => $request->input('sub_category'),
            'cost_price' => $request->input('cost_price'),
            'sale_price' => $request->input('sale_price'),
            'unit' => $request->input('unit'),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),

        ]);
        
        unset($register_products['id'], $register_products['created_at'], $register_products['updated_at']);

        return isset($register_products) && $register_products !== null
        ? response()->json(['Products registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Products record'], 400);
    }
}
