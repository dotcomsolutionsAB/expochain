<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TestCertificateModel;
use App\Models\TestCertificateProductsModel;

class TestCertificateController extends Controller
{
    //
    // create
    public function add_test_certificate(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'sales_invoice_no' => 'required|string',
            'reference_no' => 'required|string',
            'tc_date' => 'required|date',
            'seller' => 'required|string',
            'client_flag' => 'required|boolean',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.sales_invoice_no' => 'required|string'
        ]);
    
    
        $register_test_certificate = TestCertificateModel::create([
            'client_id' => $request->input('client_id'),
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'reference_no' => $request->input('reference_no'),
            'tc_date' => $request->input('tc_date'),
            'seller' => $request->input('seller'),
            'client_flag' => $request->input('client_flag'),
            'log_user' => $request->input('log_user'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            TestCertificateProductsModel::create([
                'tc_id' => $register_test_certificate['id'],
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'quantity' => $product['quantity'],
                'sales_invoice_no' => $product['sales_invoice_no'],
            ]);
        }

        unset($register_test_certificate['id'], $register_test_certificate['created_at'], $register_test_certificate['updated_at']);
    
        return isset($register_test_certificate) && $register_test_certificate !== null
        ? response()->json(['Credit Note registered successfully!', 'data' => $register_test_certificate], 201)
        : response()->json(['Failed to register Credit Note record'], 400);
    }
}
