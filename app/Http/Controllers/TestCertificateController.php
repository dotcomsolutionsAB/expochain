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

    // view
    public function view_test_certificate()
    {
        $get_test_certificates = TestCertificateModel::with(['products' => function ($query) {
            $query->select('tc_id', 'product_id', 'product_name', 'quantity', 'sales_invoice_no');
        }])
        ->select('id', 'client_id', 'sales_invoice_no', 'reference_no', 'tc_date', 'seller', 'client_flag', 'log_user')
        ->get();

        return isset($get_test_certificates) && $get_test_certificates !== null
            ? response()->json(['Test Certificates fetched successfully!', 'data' => $get_test_certificates], 200)
            : response()->json(['Failed to fetch Test Certificate data'], 404);
    }

    // update
    public function edit_test_certificate(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'sales_invoice_no' => 'required|string',
            'reference_no' => 'required|string',
            'tc_date' => 'required|date',
            'seller' => 'required|string',
            'client_flag' => 'required|boolean',
            'log_user' => 'required|string',
            'products' => 'required|array',
            'products.*.tc_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.sales_invoice_no' => 'required|string',
        ]);

        $testCertificate = TestCertificateModel::where('id', $id)->first();

        $testCertificateUpdated = $testCertificate->update([
            'client_id' => $request->input('client_id'),
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'reference_no' => $request->input('reference_no'),
            'tc_date' => $request->input('tc_date'),
            'seller' => $request->input('seller'),
            'client_flag' => $request->input('client_flag'),
            'log_user' => $request->input('log_user'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = TestCertificateProductsModel::where('tc_id', $productData['tc_id'])
                                                        ->where('product_id', $productData['product_id'])
                                                        ->first();

            if ($existingProduct) {
                // Update existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'quantity' => $productData['quantity'],
                    'sales_invoice_no' => $productData['sales_invoice_no'],
                ]);
            } else {
                // Create new product
                TestCertificateProductsModel::create([
                    'tc_id' => $productData['tc_id'],
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'quantity' => $productData['quantity'],
                    'sales_invoice_no' => $productData['sales_invoice_no'],
                ]);
            }
        }

        // Delete products not included in the request
        $productsDeleted = TestCertificateProductsModel::where('tc_id', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        unset($testCertificate['created_at'], $testCertificate['updated_at']);

        return ($testCertificateUpdated || $productsDeleted)
            ? response()->json(['message' => 'Test Certificate and products updated successfully!', 'data' => $testCertificate], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_test_certificate($id)
    {
        $get_test_certificate_id = TestCertificateModel::select('id')->where('id', $id)->first();

        if ($get_test_certificate_id) {
            $delete_test_certificate = TestCertificateModel::where('id', $id)->delete();

            $delete_test_certificate_products = TestCertificateProductsModel::where('tc_id', $get_test_certificate_id->id)->delete();

            return $delete_test_certificate && $delete_test_certificate_products
                ? response()->json(['message' => 'Test Certificate and associated products deleted successfully!'], 200)
                : response()->json(['message' => 'Failed to delete Test Certificate or products.'], 400);
        } else {
            return response()->json(['message' => 'Test Certificate not found.'], 404);
        }
    }
}
