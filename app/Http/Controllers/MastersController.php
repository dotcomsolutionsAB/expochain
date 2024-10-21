<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductsModel;
use App\Models\FinancialYearModel;
use App\Models\PdfTemplateModel;
use App\Models\GodownModel;
use App\Models\CategoryModel;


class MastersController extends Controller
{
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

    public function view_products()
    {
        $get_user_id = Auth::id();
        
        $get_user_details = User::select('id','name','email','mobile','address_line_1','address_line_2','city','pincode','gstin','state','country')->where('id', $get_user_id)->get();
        

        return isset($get_user_details) && $get_user_details !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_user_details], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_products(Request $request, $id)
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

        $update_products = ProductsModel::where('id', $id)
        ->update([
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
        
        return $update_products
        ? response()->json(['Products updated successfully!', 'data' => $update_products], 201)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_products($id)
    {
        // Delete the client
        $delete_products = ProductsModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_products
        ? response()->json(['message' => 'Delete Product successfully!'], 204)
        : response()->json(['message' => 'Sorry, products not found'], 400);
    }


    // finacial year table
    //create
    public function add_f_year(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'start_date' => 'required',
            'end_date' => 'required|string',
            'opening_stock' => 'required',
            'closing_stock' => 'required',
        ]);

        $register_f_year = FinancialYearModel::create([
            'name' => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'opening_stock' => $request->input('opening_stock'),
            'closing_stock' => $request->input('closing_stock'),
        ]);
        
        unset($register_f_year['id'], $register_f_year['created_at'], $register_f_year['updated_at']);

        return isset($register_f_year) && $register_f_year !== null
        ? response()->json(['Finacial year added successfully!', 'data' => $register_f_year], 201)
        : response()->json(['Failed to add fiancial year'], 400);
    }

    //view
    public function view_f_year()
    {        
        $get_f_year = FinancialYearModel::select('name','start_date','end_date','opening_stock','closing_stock')->get();
        

        return isset($get_f_year) && $get_f_year !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_f_year], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_f_year(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'start_date' => 'required',
            'end_date' => 'required|string',
            'opening_stock' => 'required',
            'closing_stock' => 'required',
        ]);

        $update_f_year = FinancialYearModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'opening_stock' => $request->input('opening_stock'),
            'closing_stock' => $request->input('closing_stock'),
        ]);
        
        return $update_f_year
        ? response()->json(['Fianacial year updated successfully!', 'data' => $update_f_year], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_f_year($id)
    {
        // Delete the client
        $delete_f_year = FinancialYearModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_f_year
        ? response()->json(['message' => 'Delete Financial Year successfully!'], 204)
        : response()->json(['message' => 'Sorry, Financial Year not found'], 400);
    }

    // pdf setup table
    //create
    public function add_pdf_template(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone_number' => 'required|string',
            'mobile' => 'required|string',
            'email' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required',
            'state' => 'required',
            'country' => 'required',
            'gstin' => 'required|string',
            'bank_number' => 'required',
            'bank_account_name' => 'required',
            'bank_account_number' => 'required',
            'bank_ifsc' => 'required',
            'header' => 'required',
            'footer' => 'required',

        ]);

        $register_pdf_template = PdfTemplateModel::create([
            'name' => $request->input('name'),
            'phone_number' => $request->input('phone_number'),
            'email	' => $request->input('email'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
            'bank_number' => $request->input('bank_number'),
            'bank_account_name' => $request->input('bank_account_name'),
            'bank_account_number' => $request->input('bank_account_number'),
            'bank_ifsc' => $request->input('bank_ifsc'),
            'header' => $request->input('header'),
            'footer' => $request->input('footer'),
        ]);
        
        unset($register_pdf_template['id'], $register_pdf_template['created_at'], $register_pdf_template['updated_at']);

        return isset($register_pdf_template) && $register_pdf_template !== null
        ? response()->json(['Pdf Template registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Pdf Template record'], 400);
    }

    //view
    public function pdf_template()
    {        
        $get_pdf_template = PdfTemplateModel::select('name','phone_number','mobile','email','address_line_1', 'address_line_2','city','pincode','state','country', 'gstin', 'bank_number', 'bank_account_name', 'bank_account_number', 'bank_ifsc','header', 'footer')->get();
        

        return isset($get_pdf_template) && $get_pdf_template !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_pdf_template], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_pdf_template(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'phone_number' => 'required|string',
            'mobile' => 'required|string',
            'email' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required',
            'state' => 'required',
            'country' => 'required',
            'gstin' => 'required|string',
            'bank_number' => 'required',
            'bank_account_name' => 'required',
            'bank_account_number' => 'required',
            'bank_ifsc' => 'required',
            'header' => 'required',
            'footer' => 'required',
        ]);

        $update_pdf_template = PdfTemplateModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'phone_number' => $request->input('phone_number'),
            'mobile	' => $request->input('mobile'),
            'email' => $request->input('email'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'gstin' => $request->input('gstin'),
            'bank_number' => $request->input('bank_number'),
            'bank_account_name' => $request->input('bank_account_name'),
            'bank_account_number' => $request->input('bank_account_number'),
            'bank_ifsc' => $request->input('bank_ifsc'),
            'header' => $request->input('header'),
            'footer' => $request->input('footer'),
        ]);
        
        return $update_pdf_template
        ? response()->json(['Products updated successfully!', 'data' => $update_pdf_template], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_pdf_template($id)
    {
        // Delete the client
        $delete_pdf_template = PdfTemplateModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_pdf_template
        ? response()->json(['message' => 'Delete Pdf template successfully!'], 204)
        : response()->json(['message' => 'Sorry, Pdf Template not found'], 400);
    }

    // uploads setup table
    //create
    // public function add_pdf_template(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'phone_number' => 'required|string',
    //         'mobile' => 'required|string',
    //         'email' => 'required|string',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required',
    //         'state' => 'required',
    //         'country' => 'required',
    //         'gstin' => 'required|string',
    //         'bank_number' => 'required',
    //         'bank_account_name' => 'required',
    //         'bank_account_number' => 'required',
    //         'bank_ifsc' => 'required',
    //         'header' => 'required',
    //         'footer' => 'required',

    //     ]);

    //     $register_pdf_template = PdfTemplateModel::create([
    //         'name' => $request->input('name'),
    //         'phone_number' => $request->input('phone_number'),
    //         'email	' => $request->input('email'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'gstin' => $request->input('gstin'),
    //         'bank_number' => $request->input('bank_number'),
    //         'bank_account_name' => $request->input('bank_account_name'),
    //         'bank_account_number' => $request->input('bank_account_number'),
    //         'bank_ifsc' => $request->input('bank_ifsc'),
    //         'header' => $request->input('header'),
    //         'footer' => $request->input('footer'),
    //     ]);
        
    //     unset($register_pdf_template['id'], $register_pdf_template['created_at'], $register_pdf_template['updated_at']);

    //     return isset($register_pdf_template) && $register_pdf_template !== null
    //     ? response()->json(['Pdf Template registered successfully!', 'data' => $register_products], 201)
    //     : response()->json(['Failed to register Pdf Template record'], 400);
    // }

    // //view
    // public function pdf_template()
    // {        
    //     $get_pdf_template = PdfTemplateModel::select('name','phone_number','mobile','email','address_line_1', 'address_line_2','city','pincode','state','country', 'gstin', 'bank_number', 'bank_account_name', 'bank_account_number', 'bank_ifsc','header', 'footer')->get();
        

    //     return isset($get_pdf_template) && $get_pdf_template !== null
    //     ? response()->json(['Fetch data successfully!', 'data' => $get_pdf_template], 201)
    //     : response()->json(['Failed to fetch data'], 400); 
    // }

    // // update
    // public function edit_pdf_template(Request $request, $id)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'phone_number' => 'required|string',
    //         'mobile' => 'required|string',
    //         'email' => 'required|string',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required',
    //         'state' => 'required',
    //         'country' => 'required',
    //         'gstin' => 'required|string',
    //         'bank_number' => 'required',
    //         'bank_account_name' => 'required',
    //         'bank_account_number' => 'required',
    //         'bank_ifsc' => 'required',
    //         'header' => 'required',
    //         'footer' => 'required',
    //     ]);

    //     $update_pdf_template = PdfTemplateModel::where('id', $id)
    //     ->update([
    //         'name' => $request->input('name'),
    //         'phone_number' => $request->input('phone_number'),
    //         'mobile	' => $request->input('mobile'),
    //         'email' => $request->input('email'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'gstin' => $request->input('gstin'),
    //         'bank_number' => $request->input('bank_number'),
    //         'bank_account_name' => $request->input('bank_account_name'),
    //         'bank_account_number' => $request->input('bank_account_number'),
    //         'bank_ifsc' => $request->input('bank_ifsc'),
    //         'header' => $request->input('header'),
    //         'footer' => $request->input('footer'),
    //     ]);
        
    //     return $update_pdf_template
    //     ? response()->json(['Products updated successfully!', 'data' => $update_pdf_template], 200)
    //     : response()->json(['No changes detected'], 204);
    // }

    // // delete
    // public function delete_pdf_template($id)
    // {
    //     // Delete the client
    //     $delete_pdf_template = PdfTemplateModel::where('id', $id)->delete();

    //     // Return success response if deletion was successful
    //     return $delete_pdf_template
    //     ? response()->json(['message' => 'Delete Pdf template successfully!'], 204)
    //     : response()->json(['message' => 'Sorry, Pdf Template not found'], 400);
    // }

    // godown setup table
    //create
    public function add_godown(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'mobile' => 'required|string',
            'email' => 'required|string',

        ]);

        $register_godown = GodownModel::create([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'mobile' => $request->input('email'),
            'email' => $request->input('email'),
        ]);
        
        unset($register_godown['id'], $register_godown['created_at'], $register_godown['updated_at']);

        return isset($register_godown) && $register_godown !== null
        ? response()->json(['Godown registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Godown record'], 400);
    }

    //view
    public function pdf_godown()
    {        
        $get_godown = GodownModel::select('name','address','mobile','email')->get();
        

        return isset($get_godown) && $get_godown !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_godown], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_godown(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'mobile' => 'required|string',
            'email' => 'required|string',
        ]);

        $update_pdf_template = GodownModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'mobile' => $request->input('email'),
            'email' => $request->input('email'),
        ]);
        
        return $update_pdf_template
        ? response()->json(['Products updated successfully!', 'data' => $update_pdf_template], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_godown($id)
    {
        // Delete the client
        $delete_godown = GodownModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_godown
        ? response()->json(['message' => 'Delete Godown successfully!'], 204)
        : response()->json(['message' => 'Sorry, Godown not found'], 400);
    }

    // category table
    //create
    public function add_category(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $register_category = CategoryModel::create([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        unset($register_category['id'], $register_category['created_at'], $register_category['updated_at']);

        return isset($register_category) && $register_category !== null
        ? response()->json(['Category registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Category record'], 400);
    }

    //view
    public function view_category()
    {        
        $get_category = CategoryModel::select('serial_number','name','logo')->get();
        

        return isset($get_category) && $get_category !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_category], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_category(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_category = CategoryModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_category
        ? response()->json(['Category updated successfully!', 'data' => $update_category], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_category($id)
    {
        // Delete the client
        $delete_category = CategoryModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_category
        ? response()->json(['message' => 'Delete category successfully!'], 204)
        : response()->json(['message' => 'Sorry, category not found'], 400);
    }

    // sub-category table
    //create
    public function add_sub_category(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $register_sub_category = SubCategoryModel::create([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        unset($register_sub_categoryy['id'], $register_sub_category['created_at'], $register_category['updated_at']);

        return isset($register_sub_category) && $register_sub_category !== null
        ? response()->json(['Sub Category registered successfully!', 'data' => $register_sub_category], 201)
        : response()->json(['Failed to register Sub Category record'], 400);
    }

    //view
    public function view_sub_category()
    {        
        $get_sub_category = SubCategoryModel::select('serial_number','name','logo')->get();
        

        return isset($get_sub_category) && $get_sub_category !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_sub_category], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_sub_category(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_sub_category = SubCategoryModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_sub_category
        ? response()->json(['Sub-Category updated successfully!', 'data' => $update_sub_category], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_sub_category($id)
    {
        // Delete the client
        $delete_sub_category = SubCategoryModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_category
        ? response()->json(['message' => 'Delete sub-category successfully!'], 204)
        : response()->json(['message' => 'Sorry, sub-category not found'], 400);
    }

    // Brand table
    //create
    public function add_brand(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $register_category = BrandModel::create([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        unset($register_category['id'], $register_category['created_at'], $register_category['updated_at']);

        return isset($register_category) && $register_category !== null
        ? response()->json(['Category registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Category record'], 400);
    }

    //view
    public function view_brand()
    {        
        $get_brand = BrandModel::select('serial_number','name','logo')->get();

        return isset($get_brand) && $get_brand!== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_brand], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_brand(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_brand = BrandModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_brand
        ? response()->json(['Brand updated successfully!', 'data' => $update_brand], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_brand($id)
    {
        // Delete the client
        $delete_brand = BrandModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_brand
        ? response()->json(['message' => 'Delete brand successfully!'], 204)
        : response()->json(['message' => 'Sorry, category not found'], 400);
    }
}
