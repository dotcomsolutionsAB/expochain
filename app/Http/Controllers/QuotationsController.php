<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\QuotationsModel;
use App\Models\QuotationProductsModel;
use App\Models\QuotationAddonsModel;
use App\Models\QuotationTermsModel;
use App\Models\ClientsModel;
use App\Models\ClientContactsModel;
use App\Models\DiscountModel;
use App\Models\ProductsModel;
use App\Models\ClientAddressModel;
use App\Models\CounterModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Auth;
use Carbon\Carbon;
use DB;
use NumberFormatter;

class QuotationsController extends Controller
{
    //

    // create
    public function add_quotations(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            // 'client_contact_id' => 'nullable|integer|exists:t_client_contacts,id',
            'quotation_no' => 'nullable|string|max:255',
            'quotation_date' => 'required|date_format:Y-m-d',
            'enquiry_no' => 'required|string|max:255',
            'enquiry_date' => 'required|date',
            // 'status' => 'nullable|in:pending,completed,rejected', // Allow status but it's optional
            'sales_person' => 'required|exists:users,id',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            // 'discount' => 'nullable|numeric|min:0',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',

            // for products
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.unit' => 'required|string|max:50',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'required|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'nullable|string|max:255',
            'products.*.tax' => 'required|numeric|min:0',
            'products.*.cgst' => 'nullable|numeric|min:0',
            'products.*.sgst' => 'nullable|numeric|min:0',
            'products.*.igst' => 'nullable|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'products.*.delivery' => 'nullable|string|max:255',
            'products.*.attachment' => 'nullable|string|max:255',

            // for add-ons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',

            // for terms
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string|max:255',
            'terms.*.value' => 'required|string|max:255',
        ]);

        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'Quotation',
            // 'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $quotation_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $quotation_no = $request->input('quotation_no');
        }

        $exists = QuotationsModel::where('company_id', Auth::user()->company_id)
            ->where('quotation_no', $quotation_no)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and quotation_no must be unique.',
            ], 422);
        }

        // Retrieve client contact and address
        // $client_contact_id = $request->input('client_contact_id') ?? ClientsModel::where('id', $request->input('client_id'))->value('default_contact');

        $get_customer_data = ClientsModel::select('name', 'customer_id')
            ->where('id', $request->input('client_id'))
            ->first();

        // $client_address_record = ClientAddressModel::select('address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country')
        //     ->where('customer_id', $get_customer_data->customer_id)
        //     ->where('type', 'billing')
        //     ->first();

        // $currentDate = Carbon::now()->toDateString();

        // Create quotation
        $register_quotations = QuotationsModel::create([
            'client_id' => $request->input('client_id'),
            // 'client_contact_id' => $client_contact_id,
            'company_id' => Auth::user()->company_id,
            'name' => $get_customer_data->name,
            'quotation_no' => $quotation_no,
            'quotation_date' => $request->input('quotation_date'),
            'enquiry_no' => $request->input('enquiry_no'),
            'enquiry_date' => $request->input('enquiry_date'),
            'template' => $request->input('template'),
            'contact_person' => $request->input('contact_person'),
            'sales_person' => $request->input('sales_person'),
            'status' => 'pending', // Default to 'pending'
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
        ]);

        foreach ($request->input('products') as $product) {
            QuotationProductsModel::create([
                'quotation_id' => $register_quotations['id'],
                'company_id' => Auth::user()->company_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'quantity' => $product['quantity'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'amount' => $product['amount'],
                'delivery' => $product['delivery'],
                'discount_type' => $product['discount_type'],
                'discount' => $product['discount'],
                'hsn' => $product['hsn'],
                'tax' => $product['tax'],
                'cgst' => $product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
            ]);
        }

        foreach ($request->input('addons', []) as $addon) {
            QuotationAddonsModel::create([
                'quotation_id' => $register_quotations['id'],
                'company_id' => Auth::user()->company_id,
                'name' => $addon['name'],
                'amount' => $addon['amount'],
                'tax' => $addon['tax'],
                'hsn' => $addon['hsn'],
                'cgst' => $addon['cgst'],
                'sgst' => $addon['sgst'],
                'igst' => $addon['igst'],
            ]);
        }

        foreach ($request->input('terms', []) as $term) {
            QuotationTermsModel::create([
                'quotation_id' => $register_quotations['id'],
                'company_id' => Auth::user()->company_id,
                'name' => $term['name'],
                'value' => $term['value'],
            ]);
        }

        CounterModel::where('name', 'Quotation Counter')
            ->where('company_id', Auth::user()->company_id)
            ->increment('next_number');

        unset($register_quotations['id'], $register_quotations['created_at'], $register_quotations['updated_at']);

        // return response()->json([
        //     'code' => 201,
        //     'success' => true,
        //     'message' => 'Quotations registered successfully!',
        //     'data' => $register_quotations,
        // ], 201);
        return isset($register_quotations) && $register_quotations !== null
            ? response()->json(['code' => 201, 'success' => true, 'message' => 'Quotations registered successfully!', 'data' => $register_quotations], 201)
            : response()->json(['code' => 400, 'success' => false,'Failed to register quotations record'], 400);
    }

    // fetch
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }

    public function view_quotations(Request $request)
    {
        // Enable Query Logging
        // DB::enableQueryLog();

        // Get filter inputs
        $clientId = $request->input('client_id');
        $clientContactId = $request->input('client_contact_id');
        $name = $request->input('name');
        $quotationNo = $request->input('quotation_no');
        $quotationDate = $request->input('quotation_date');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $enquiryNo = $request->input('enquiry_no');
        $enquiryDate = $request->input('enquiry_date');
        $user = $request->input('user');
        $status = $request->input('status');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Get total count of records in ` t_quotations`
        $total_quotations = QuotationsModel::count(); 

        // Build the query
        $query = QuotationsModel::with(['products' => function ($query) {
            $query->select('quotation_id', 'product_id', 'product_name', 'description', 'quantity', 'amount', 'unit', 'price', 'delivery', 'discount_type', 'discount', 'hsn', DB::raw('(tax / 2) as cgst_rate'), DB::raw('(tax / 2) as sgst_rate'), DB::raw('(tax) as igst_rate'), 'cgst', 'sgst', 'igst','attachment');
            }, 'addons' => function ($query) {
                $query->select('quotation_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            }, 'terms' => function ($query) {
                $query->select('quotation_id', 'name', 'value');
            },
                'get_user' => function ($query) { // Fetch only user name
                    $query->select('id', 'name');
            },
                'get_template' => function ($query) { // Fetch template id and name
                $query->select('id', 'name');
            },
                'salesPerson' => function ($query) {  // New addition
                $query->select('id', 'name');
            }
        ])
        ->select('id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'quotation_no', DB::raw('DATE_FORMAT(quotation_date, "%d-%m-%Y") as quotation_date'), 'status', 'user', 'enquiry_no', DB::raw('DATE_FORMAT(enquiry_date, "%d-%m-%Y") as enquiry_date'), 'sales_person', 'discount', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'contact_person')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if ($clientContactId) {
            $query->where('client_contact_id', $clientContactId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($quotationNo) {
            $query->where('quotation_no', 'LIKE', '%' . $quotationNo . '%');
        }
        if ($quotationDate) {
            $query->whereDate('quotation_date', $quotationDate);
        }
        if ($enquiryNo) {
            $query->where('enquiry_no', 'LIKE', '%' . $enquiryNo . '%');
        }
        if ($enquiryDate) {
            $query->whereDate('enquiry_date', $enquiryDate);
        }

        if ($dateFrom && $dateTo) {
            $query->whereBetween('quotation_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->whereDate('quotation_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('quotation_date', '<=', $dateTo);
        }

        if ($user) {
            $query->whereDate('user', $user);
        }
        if ($status) {
            $query->whereDate('status', $status);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_quotations = $query->get();

        // dd($get_quotations);
        // **DEBUG: Get SQL Query**
        // $queries = DB::getQueryLog();
        // dd($queries, $get_quotations); // Print the SQL Query + Result

        // Transform Data
        $get_quotations->transform(function ($quotation) {

            // Convert total to words
            $quotation->amount_in_words = $this->convertNumberToWords($quotation->total);

            // Capitalize the first letter of status
            $quotation->status = ucfirst($quotation->status);

            // Replace user ID with user object
            $quotation->user = isset($quotation->get_user) ? [
                'id' => $quotation->get_user->id,
                'name' => $quotation->get_user->name
            ] : ['id' => null, 'name' => 'Unknown'];
            unset($quotation->get_user);

            // ✅ New Fix for sales_person
            $quotation->sales_person = isset($quotation->salesPerson) ? [
                'id' => $quotation->salesPerson->id,
                'name' => $quotation->salesPerson->name
            ] : ['id' => null, 'name' => 'Unknown'];
            unset($quotation->salesPerson);

            // Replace template ID with template object
            $quotation->template = isset($quotation->get_template) ? [
                'id' => $quotation->get_template->id,
                'name' => $quotation->get_template->name
            ] : ['id' => null, 'name' => 'Unknown'];
            unset($quotation->get_template); // Remove user object after fetching the name

            // **Remove `quotation_id` from products**
            $quotation->products->transform(function ($product) {
                unset($product->quotation_id);
                return $product;
            });

            // **Remove `quotation_id` from addons**
            $quotation->addons->transform(function ($addon) {
                unset($addon->quotation_id);
                return $addon;
            });

            // **Remove `quotation_id` from terms**
            $quotation->terms->transform(function ($term) {
                unset($term->quotation_id);
                return $term;
            });

            return $quotation;
        });

        // Return response
        return $get_quotations->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Quotations fetched successfully!',
                'data' => $get_quotations,
                'fetched_records' => $get_quotations->count(),
                'count' => $total_quotations,
            ], 200)
            : response()->json([
                'code' => 200,
                'success' => true,
                'data' => [],
                'message' => 'No Quotations found!',
                'count' => 0,
            ], 200);
    }

    // Update Quotations
    public function update_quotations(Request $request, $id)
    {
        $request->validate([
            // 'quotation_id' => 'required|integer',
            'client_id' => 'required|integer',
            'client_contact_id' => 'required|integer',
            // 'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'quotation_no' => 'nullable|string|max:255',
            'quotation_date' => 'required|date',
            'enquiry_no' => 'required|string',
            'enquiry_date' => 'required|date',
            'sales_person' => 'required|exists:users,id',
            // 'sales_contact' => 'required|string',
            // 'sales_email' => 'required|string',
            'discount' => 'required|numeric',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer|exists:t_pdf_template,id',

            // for products
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.amount' => 'required|numeric',
            'products.*.delivery' => 'nullable|string|max:255',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.attachment' => 'required|string',

             // for addons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',

             // for terms
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string',
            'terms.*.value' => 'required|string',
        ]);

        $quotation = QuotationsModel::where('id', $id)->first();

        if (!$quotation) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Quotation not found!'], 404);
        }

        $quotationUpdated = $quotation->update([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            // 'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            // 'quotation_no' => $request->input('quotation_date'),
            'quotation_date' => $request->input('quotation_date'),
            'enquiry_no' => $request->input('enquiry_no'),
            'enquiry_date' => $request->input('enquiry_date'),
            'sales_person' => $request->input('sales_person'),
            // 'sales_person' => Auth::user()->name,
            // 'sales_contact' => Auth::user()->mobile,
            // 'sales_email' => Auth::user()->email,
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

            $existingProduct = QuotationProductsModel::where('quotation_id', $id)
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'amount' => $productData['amount'],
                    'delivery' => $productData['delivery'],
                    'discount_type' => $productData['discount_type'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                ]);
            } else {
                QuotationProductsModel::create([
                    'quotation_id' => $id,
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
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

            $existingAddon = QuotationAddonsModel::where('quotation_id', $id)
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
                    'quotation_id' => $id,
                    'company_id' => Auth::user()->company_id,
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

            $existingTerm = QuotationTermsModel::where('quotation_id', $id)
                                            ->where('name', $termData['name'])
                                            ->first();

            if ($existingTerm) {
                $existingTerm->update([
                    'value' => $termData['value'],
                ]);
            } else {
                QuotationTermsModel::create([
                    'quotation_id' => $id,
                    'company_id' => Auth::user()->company_id,
                    'name' => $termData['name'],
                    'value' => $termData['value'],
                ]);
            }
        }

        // Delete products, addons, and terms not included in the request
        QuotationProductsModel::where('quotation_id', $id)
                            ->whereNotIn('product_id', $requestProductIDs)
                            ->delete();

        QuotationAddonsModel::where('quotation_id', $id)
                            ->whereNotIn('name', $requestAddonIDs)
                            ->delete();

        QuotationTermsModel::where('quotation_id', $id)
                        ->whereNotIn('name', $requestTermIDs)
                        ->delete();

        return ($quotationUpdated)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Quotation, products, addons, and terms updated successfully!', 'data' => $quotation], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // Delete Quotations
    public function delete_quotations($id)
    {
        $company_id = Auth::user()->company_id;

        $delete_quotation = QuotationsModel::where('id', $id)
                                            ->where('company_id', $company_id)
                                            ->delete();
        $delete_quotation_products = QuotationProductsModel::where('quotation_id', $id)
                                                            ->where('company_id', $company_id)
                                                            ->delete();
        $delete_quotation_addons = QuotationAddonsModel::where('quotation_id', $id)
                                                        ->where('company_id', $company_id)
                                                        ->delete();
        $delete_quotation_terms = QuotationTermsModel::where('quotation_id', $id)
                                                       ->where('company_id', $company_id)
                                                       ->delete();

        return $delete_quotation && $delete_quotation_products && $delete_quotation_addons && $delete_quotation_terms
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Quotation and associated data deleted successfully!'], 200)
            : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete quotation or associated data.'], 400);
    }

    // migrate data
    public function importQuotations()
    {
        // Increase memory and execution time for large imports
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '1024M');   // Increase memory limit

        // Clear old data before import
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

        $batchSize = 10; // Optimal batch size

        // Batching setup
        $quotationsBatch = [];
        $productsBatch = [];
        $addonsBatch = [];
        $termsBatch = [];

        // **1️⃣ Collect Quotation Data**
        foreach ($data as $record) {
            // Decode JSON fields
            $enquiryData = json_decode($record['enq_no_date'] ?? '{}', true);
            $taxData = json_decode($record['tax'] ?? '{}', true);

            // Get client data
            $client = ClientsModel::where('name', $record['Client'])->first();
            $client_contact = ClientContactsModel::select('id')->where('customer_id', $client->customer_id ?? 0)->first();

            $statusMap = [
                0 => 'pending',
                1 => 'completed',
                2 => 'rejected'
            ];

            $igst = array_key_exists('igst', $taxData) ? (float)$taxData['igst'] : 0;
            $cgst = array_key_exists('cgst', $taxData) ? (float)$taxData['cgst'] : 0;
            $sgst = array_key_exists('sgst', $taxData) ? (float)$taxData['sgst'] : 0;

            // Prepare quotation data
            $quotationsBatch[] = [
                'company_id' => Auth::user()->company_id,
                'client_id' => $client->id ?? null,
                'client_contact_id' => $client_contact->id ?? null,
                'name' => $record['Client'] ?? null,
                // 'address_line_1' => $client->address_line_1 ?? null,
                // 'address_line_2' => $client->address_line_2 ?? null,
                // 'city' => $client->city ?? null,
                // 'pincode' => $client->pincode ?? null,
                // 'state' => $client->state ?? null,
                // 'country' => $client->country ?? 'India',
                'quotation_no' => $record['quotation_no'],
                'quotation_date' => !empty($record['quotation_date']) 
                    ? date('Y-m-d', strtotime($record['quotation_date'])) 
                    : null,
                'status' => $statusMap[$record['Status']] ?? 'pending',
                'user' => Auth::user()->id,
                'enquiry_no' => $enquiryData['enquiry_no'] ?? null,
                'enquiry_date' => !empty($enquiryData['enquiry_date']) 
                    ? date('Y-m-d', strtotime($enquiryData['enquiry_date'])) 
                    : null,
                'discount' => is_numeric($record['discount']) ? (float) $record['discount'] : 0,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'total' => is_numeric($record['total']) ? (float) $record['total'] : 0,
                'currency' => $record['currency'] ?? 'INR',
                'template' => json_decode($record['template'], true)['id'] ?? '0',
                'created_at' => now(),
                'updated_at' => now()
            ];

        }

        // **2️⃣ Batch Insert Quotations & Fetch IDs**
        foreach (array_chunk($quotationsBatch, $batchSize) as $chunk) {
            QuotationsModel::insert($chunk);
        }

        // Fetch newly inserted IDs
        $quotationIds = QuotationsModel::whereIn('quotation_no', array_column($quotationsBatch, 'quotation_no'))
        ->pluck('id', 'quotation_no')
        ->toArray();


        // **3️⃣ Insert Related Products, Addons, and Terms**
        foreach ($data as $record) {
            $quotationId = $quotationIds[$record['quotation_no']] ?? null;
            if (!$quotationId) {
                continue;
            }

            // Decode JSON fields again inside the loop
            $itemsData = json_decode($record['items'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);
            $termsData = json_decode($record['Terms'] ?? '{}', true);

            // if (!is_array($addonsData) || !is_array($itemsData) || !is_array($termsData)) {
            //     continue;
            // }

            foreach ($itemsData['product'] as $index => $product) {
                // dd($itemsData['delivery'][$index]);
                // dd($itemsData['attachment'][$index]);
                $productsBatch[] = [
                    'quotation_id' => $quotationId,
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $index + 1,
                    'product_name' => $itemsData['product'][$index] ?? 'Unnamed Product',
                    'description' => $itemsData['desc'][$index] ?? null,
                    'quantity' => is_numeric($itemsData['quantity'][$index]) ? (int)$itemsData['quantity'][$index] : 0,
                    'unit' => $itemsData['unit'][$index] ?? null,
                    'price' => is_numeric($itemsData['price'][$index]) ? (float)$itemsData['price'][$index] : 0,
                    // Calculate the amount
                    'amount' => (
                        (isset($itemsData['quantity'][$index]) ? (float) $itemsData['quantity'][$index] : 0.0) *
                        (
                            (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0) -
                            (
                                ((isset($itemsData['discount'][$index]) ? (float) $itemsData['discount'][$index] : 0.0) *
                                (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0)) / 100
                            )
                        )
                    ) + (
                        (isset($itemsData['cgst'][$index]) ? (float) $itemsData['cgst'][$index] : 0.0) +
                        (isset($itemsData['sgst'][$index]) ? (float) $itemsData['sgst'][$index] : 0.0) +
                        (isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0.0)
                    ),
                    'delivery' => isset($itemsData['delivery'][$index]) 
                        ? (is_array($itemsData['delivery'][$index]) 
                            ? (empty(array_filter($itemsData['delivery'][$index])) ? null : implode(', ', $itemsData['delivery'][$index])) 
                            : trim($itemsData['delivery'][$index])) 
                        : null,
                    'discount_type' => 'percentage',
                    'discount' => is_numeric($itemsData['discount'][$index]) ? (float)$itemsData['discount'][$index] : 0,
                    'hsn' => $itemsData['hsn'][$index] ?? null,
                    'tax' => is_numeric($itemsData['tax'][$index] ?? null) ? (float)$itemsData['tax'][$index] : 0,
                    'cgst' => is_numeric($itemsData['cgst'][$index] ?? null) ? (float)$itemsData['cgst'][$index] : 0,
                    'sgst' => is_numeric($itemsData['sgst'][$index] ?? null) ? (float)$itemsData['sgst'][$index] : 0,
                    'igst' => is_numeric($itemsData['igst'][$index] ?? null) ? (float)$itemsData['igst'][$index] : 0,
                    'attachment' => isset($itemsData['attachment'][$index]) && trim($itemsData['attachment'][$index]) !== ''
                        ? (is_array($itemsData['attachment'][$index]) 
                            ? (empty(array_filter($itemsData['attachment'][$index])) ? null : json_encode($itemsData['attachment'][$index])) 
                            : $itemsData['attachment'][$index]) 
                        : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            foreach ($addonsData as $name => $values) {
                $addonsBatch[] = [
                    'quotation_id' => $quotationId,
                    'company_id' => Auth::user()->company_id,
                    'name' => $name,
                    'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
                    'tax' => 18,
                    'hsn' => $values['hsn'] ?? null,
                    'cgst' => (float)($values['cgst'] ?? 0),
                    'sgst' => (float)($values['sgst'] ?? 0),
                    'igst' => (float)($values['igst'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            foreach ($termsData as $name => $value) {
                $termsBatch[] = [
                    'quotation_id' => $quotationId,
                    'company_id' => Auth::user()->company_id,
                    'name' => $name,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // **4️⃣ Batch Insert Products, Addons, and Terms**
        foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
            QuotationProductsModel::insert($chunk);
        }
        foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
            QuotationAddonsModel::insert($chunk);
        }
        foreach (array_chunk($termsBatch, $batchSize) as $chunk) {
            QuotationTermsModel::insert($chunk);
        }

        // **4️⃣ Batch Insert**
        // QuotationProductsModel::insert($productsBatch);
        // QuotationAddonsModel::insert($addonsBatch);
        // QuotationTermsModel::insert($termsBatch);

        return response()->json(['message' => 'Import successful'], 200);
    }

    // update quotation status
    public function updateQuotationStatus(Request $request, $id)
    {
        // Validate request
        $request->validate([
            'status' => 'required|string|in:pending,completed,rejected',
        ]);

        // Fetch the quotation
        $quotation = QuotationsModel::where('id', $id)
            ->where('company_id', Auth::user()->company_id) // Ensuring company-based validation
            ->first();

        if (!$quotation) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Quotation not found!',
            ], 404);
        }

        // Update the status
        $quotation->update([
            'status' => $request->status
        ]);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Quotation status updated successfully!',
            'data' => [
                'quotation_id' => $quotation->id,
                'status' => ucfirst($quotation->status), // Capitalizing first letter
            ]
        ], 200);
    }

}
