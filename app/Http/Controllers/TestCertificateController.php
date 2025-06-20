<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\TestCertificateModel;
use App\Models\TestCertificateProductsModel;
use App\Models\ClientsModel;
use App\Models\ProductsModel;
use Carbon\Carbon;
use Auth;

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
            'seller' => 'required|string',
            'client_flag' => 'required|boolean',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|integer',
            'products.*.sales_invoice_no' => 'required|string'
        ]);
    
        $currentDate = Carbon::now()->toDateString();

        $register_test_certificate = TestCertificateModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'reference_no' => $request->input('reference_no'),
            'tc_date' => $currentDate,
            'seller' => $request->input('seller'),
            'client_flag' => $request->input('client_flag'),
            'log_user' => $request->input('log_user'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            $product_details = ProductsModel::where('id', $product['product_id'])
                                            ->where('company_id', Auth::user()->company_id)
                                            ->first();

            if ($product_details) 
            {
                TestCertificateProductsModel::create([
                    'tc_id' => $register_test_certificate['id'],
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product['product_id'],
                    'product_name' => $product_details->name,
                    'quantity' => $product['quantity'],
                    'sales_invoice_no' => $product['sales_invoice_no'],
                ]);

            }

            else{
                return response()->json(['code' => 404,'success' => false, 'message' => 'Sorry, Products not found'], 404);
            }
        }

        unset($register_test_certificate['id'], $register_test_certificate['created_at'], $register_test_certificate['updated_at']);
    
        return isset($register_test_certificate) && $register_test_certificate !== null
        ? response()->json(['code' => 201,'success' => true,'Credit Note registered successfully!', 'data' => $register_test_certificate], 201)
        : response()->json(['code' => 400,'success' => false,'Failed to register Credit Note record'], 400);
    }

    // view
    public function view_test_certificate(Request $request)
    {
        // Get filter inputs
        $clientId = $request->input('client_id'); // Filter by client ID
        $salesInvoiceNo = $request->input('sales_invoice_no'); // Filter by sales invoice number
        $referenceNo = $request->input('reference_no'); // Filter by reference number
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = TestCertificateModel::with(['products' => function ($query) {
            $query->select('tc_id', 'product_id', 'product_name', 'quantity', 'sales_invoice_no');
        }])
        ->select('id', 'client_id', 'sales_invoice_no', 'reference_no', 'tc_date', 'seller', 'client_flag', 'log_user')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if ($salesInvoiceNo) {
            $query->where('sales_invoice_no', 'LIKE', '%' . $salesInvoiceNo . '%');
        }
        if ($referenceNo) {
            $query->where('reference_no', 'LIKE', '%' . $referenceNo . '%');
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch the data
        $get_test_certificates = $query->get();

        // Return the response
        return $get_test_certificates->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Test Certificates fetched successfully!',
                'data' => $get_test_certificates,
                'count' => $get_test_certificates->count()
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Test Certificates found!',
            ], 404);
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
            'company_id' => Auth::user()->company_id,
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
                    'company_id' => Auth::user()->company_id,
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
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Test Certificate and products updated successfully!', 'data' => $testCertificate], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_test_certificate($id)
    {
        $get_test_certificate_id = TestCertificateModel::select('id', 'company_id')->where('id', $id)->first();

        if ($get_test_certificate_id && $get_test_certificate_id->company_id === Auth::user()->company_id) {
            $delete_test_certificate = TestCertificateModel::where('id', $id)->delete();

            $delete_test_certificate_products = TestCertificateProductsModel::where('tc_id', $get_test_certificate_id->id)->delete();

            return $delete_test_certificate && $delete_test_certificate_products
                ? response()->json(['code' => 200,'success' => true, 'message' => 'Test Certificate and associated products deleted successfully!'], 200)
                : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Test Certificate or products.'], 400);
        } else {
            return response()->json(['code' => 404,'success' => false, 'message' => 'Test Certificate not found.'], 404);
        }
    }

    public function importTestCertificates()
    {
        // Increase the maximum execution time for large data sets
        set_time_limit(300);
    
        // Clear existing records from the related tables
        TestCertificateModel::truncate();
        TestCertificateProductsModel::truncate();
    
        // Define the external URL to fetch the data
        $url = 'https://expo.egsm.in/assets/custom/migrate/test_certificate.php'; // Replace with the actual URL
    
        try {
            // Fetch data from the URL
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    
        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }
    
        $data = $response->json('data');
    
        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }
    
        $successfulInserts = 0;
        $errors = [];
    
        foreach ($data as $record) {
            // Parse JSON data for items
            $itemsData = json_decode($record['items'], true);
    
            // Validate JSON structure
            if (!is_array($itemsData) || !isset($itemsData['id']) || !isset($itemsData['product_name'])) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure for items.'];
                continue;
            }
    
            // Retrieve client ID based on the name
            $client = ClientsModel::where('name', $record['client'])->first();
    
            if (!$client) {
                $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
                continue;
            }
    
            // Handle invalid or empty tc_date
            $tcDate = ($record['tc_date'] === '0000-00-00' || !strtotime($record['tc_date'])) ? now() : $record['tc_date'];
    
            // Prepare test certificate data
            $testCertificateData = [
                'company_id' => Auth::user()->company_id,
                'client_id' => $client->id,
                'sales_invoice_no' => !empty($record['si_no']) ? $record['si_no'] : 'Unknown', // Map to si_no
                'reference_no' => $record['reference_no'] ?? 'Unknown',
                'tc_date' => $tcDate, // Use default date if invalid
                'seller' => !empty($record['cgst']) ? $record['seller'] : 'Unknown', // Fetch from seller column
                'client_flag' => (bool) $record['hide_client'],
                'log_user' => $record['log_user'] ?? 'Unknown',
            ];
    
            // Validate test certificate data
            $validator = Validator::make($testCertificateData, [
                'client_id' => 'required|integer',
                'sales_invoice_no' => 'required|string',
                'reference_no' => 'required|string',
                'tc_date' => 'required|date',
                'seller' => 'required|string',
                'client_flag' => 'required|boolean',
                'log_user' => 'required|string',
            ]);
    
            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }
    
            try {
                // Insert the test certificate data
                $testCertificate = TestCertificateModel::create($testCertificateData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert test certificate: ' . $e->getMessage()];
                continue;
            }
    
            // Insert products related to the test certificate
            foreach ($itemsData['id'] as $index => $productId) {
                try {
                    TestCertificateProductsModel::create([
                        'tc_id' => $testCertificate->id,
                        'company_id' => Auth::user()->company_id,
                        'product_id' => $productId,
                        'product_name' => $itemsData['product_name'][$index] ?? 'Unknown Product',
                        'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
                        'sales_invoice_no' => $itemsData['inv'][$index] ?? 'Unknown',
                    ]);
                } catch (\Exception $e) {
                    $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
                }
            }
        }
    
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Test certificates import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
    

}
