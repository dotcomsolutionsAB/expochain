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
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Auth;
use Carbon\Carbon;
use DB;
use NumberFormatter;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Log;

class QuotationsController extends Controller
{
    //
    // create
    public function add_quotations(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'quotation_no' => 'nullable|string|max:255',
            'quotation_date' => 'required|date_format:Y-m-d',
            'enquiry_no' => 'required|string|max:255',
            'enquiry_date' => 'required|date',
            'sales_person' => 'required|exists:users,id',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            // for products
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
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
            'products.*.gross' => 'nullable|numeric|min:0',
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
            'terms.*.term_master_id' => 'required|integer|exists:t_quotation_term_masters,id',
        ]);

        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter/fetch', 'GET', [
            'name' => 'quotation',
            // 'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view($sendRequest);
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

        // // \DB::enableQueryLog();
        // $exists = QuotationsModel::where('company_id', Auth::user()->company_id)
        //     ->where('quotation_no', $quotation_no)
        //     ->exists();
        //     // dd(\DB::getQueryLog());
        //     // dd($exists);

        // if ($exists) {
        //     return response()->json([
        //         'code' => 422,
        //         'success' => false,
        //         'error' => 'The combination of company_id and quotation_no must be unique.',
        //     ], 422);
        // }

        // ==========================
        // DEBUG BLOCK – BEFORE EXIST CHECK
        // ==========================
        \Log::info('ADD_QUOTATION_DEBUG', [
            'auth_user_id'            => Auth::user()->id,
            'auth_company_id'         => Auth::user()->company_id,
            'get_customer_type'       => $get_customer_type,
            'quotation_no_from_request' => $request->input('quotation_no'),
            'final_quotation_no_used'   => $quotation_no,
        ]);

        // Check if record exists (your original logic)
        $exists = QuotationsModel::where('company_id', Auth::user()->company_id)
            ->where('quotation_no', $quotation_no)
            ->first();

        // ==========================
        // DEBUG RESPONSE WHEN EXISTS
        // ==========================
        if ($exists) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'error' => 'The combination of company_id and quotation_no must be unique.',
                'debug' => [
                    'company_id'            => Auth::user()->company_id,
                    'final_quotation_no'     => $quotation_no,
                    'existing_row_id'        => $exists->id,
                    'existing_quotation_no'  => $exists->quotation_no,
                ]
            ], 422);
        }

        $get_customer_data = ClientsModel::select('name', 'customer_id')
            ->where('id', $request->input('client_id'))
            ->first();

        // Create quotation
        $register_quotations = QuotationsModel::create([
            'client_id' => $request->input('client_id'),
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
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                'gross' => $product['gross'],
            ]);
        }

        foreach ($request->input('addons', []) as $addon) {
            QuotationAddonsModel::create([
                'quotation_id' => $register_quotations['id'],
                'company_id' => Auth::user()->company_id,
                'name' => $addon['name'],
                'amount' => $addon['amount'],
                'tax' => $addon['tax'],
                'hsn' =>  '99',
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
                'term_master_id' => $term['term_master_id'],
            ]);
        }

        // increment the `next_number` by 1
        CounterModel::where('name', 'quotation')
            ->where('company_id', Auth::user()->company_id)
            ->increment('next_number');

        unset($register_quotations['id'], $register_quotations['created_at'], $register_quotations['updated_at']);

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

    public function view_quotations(Request $request, $id = null)
    {
        try {
            // Build the base query with relationships
            $query = QuotationsModel::with([
                'products' => function ($query) {
                    $query->select(
                        'quotation_id', 'product_id', 'product_name', 'description', 'quantity', 'amount', 
                        'unit', 'price', 'delivery', 'discount_type', 'discount', 'hsn', 
                        DB::raw('(tax / 2) as cgst_rate'), 
                        DB::raw('(tax / 2) as sgst_rate'), 
                        DB::raw('(tax) as igst_rate'), 
                        'cgst', 'sgst', 'igst', 'attachment'
                    );
                },
                'addons' => function ($query) {
                    $query->select('quotation_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
                },
                // 'terms' => function ($query) {
                //     $query->select('quotation_id', 'name', 'value', 'term_master_id');
                // },
                // Load terms along with their term master details
                'terms.termMaster',
                'get_user:id,name',
                'get_template:id,name',
                'salesPerson:id,name',
                // Load the client record along with its addresses
                // 'client' => function ($q) {
                //     $q->select('id', 'name', 'customer_id', 'mobile', 'email')
                //       ->with('addresses'); // addresses() relationship should be defined in ClientsModel
                // }
                'client' => function ($q) {
                    // Only select the key columns needed for the join (ID and customer_id)
                    $q->select('id', 'customer_id')
                    ->with(['addresses' => function ($query) {
                        // Only fetch the customer_id (for joining) and the state field
                        $query->select('customer_id', 'state');
                    }]);
                }

            ])
            ->select(
                'id', 'client_id', 'name', 'quotation_no', 'quotation_date',
                DB::raw('DATE_FORMAT(quotation_date, "%d-%m-%Y") as quotation_date_formatted'), 
                'enquiry_no', 
                DB::raw('DATE_FORMAT(enquiry_date, "%d-%m-%Y") as enquiry_date'), 
                'template', 'contact_person', 'sales_person', 'status', 'user', 'cgst', 'sgst', 'igst', 'total', 'currency', 'gross', 'round_off'
            )
            ->where('company_id', Auth::user()->company_id);

            // If an ID is provided, fetch a single quotation
            if ($id) {
                $quotation = $query->where('id', $id)->first();
                if (!$quotation) {
                    return response()->json([
                        'code' => 404,
                        'success' => false,
                        'message' => 'Quotation not found!',
                    ], 404);
                }

                // Transform single quotation
                $quotation->amount_in_words = $this->convertNumberToWords($quotation->total);
                $quotation->total = is_numeric($quotation->total) 
                                    ? number_format((float)$quotation->total, 2) 
                                    : $quotation->total;
                $quotation->user = $quotation->get_user 
                                    ? ['id' => $quotation->get_user->id, 'name' => $quotation->get_user->name] 
                                    : ['id' => null, 'name' => 'Unknown'];
                $quotation->sales_person = $quotation->salesPerson 
                                    ? ['id' => $quotation->salesPerson->id, 'name' => $quotation->salesPerson->name] 
                                    : ['id' => null, 'name' => 'Unknown'];
                $quotation->template = $quotation->get_template 
                                    ? ['id' => $quotation->get_template->id, 'name' => $quotation->get_template->name] 
                                    : ['id' => null, 'name' => 'Unknown'];
                unset($quotation->get_user, $quotation->salesPerson, $quotation->get_template);

                // Transform client: Only return state from addresses
                if ($quotation->client) {
                    $state = optional($quotation->client->addresses->first())->state;
                    $quotation->client = ['state' => $state];
                } else {
                    $quotation->client = null;
                }

                // Transform terms: Replace term_master_id with a term_master object
                $quotation->terms = $quotation->terms->map(function ($term) {
                    return [
                        'quotation_id' => $term->quotation_id,
                        'name' => $term->name,
                        'value' => $term->value,
                        'term_master' => $term->termMaster 
                            ? [
                                'id'      => $term->termMaster->id,
                                'name'    => $term->termMaster->name,
                                'default' => $term->termMaster->default,
                                'type'    => $term->termMaster->type,
                            ]
                            : null,
                    ];
                });

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Quotation fetched successfully!',
                    'data' => $quotation,
                ], 200);
            }

            // Apply filters for listing
            $clientId = $request->input('client_id');
            $name = $request->input('name');
            $quotationNo = $request->input('quotation_no');
            $quotationDate = $request->input('quotation_date');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $enquiryNo = $request->input('enquiry_no');
            $enquiryDate = $request->input('enquiry_date');
            $user = $request->input('user');
            $status = $request->input('status');
            $productIds = $request->input('product_ids');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            if ($clientId) {
                $query->where('client_id', $clientId);
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
                $query->where('user', $user);
            }
            if (!empty($status)) {
                $statusArray = explode(',', $status);
                $query->whereIn('status', $statusArray);
            }
            if (!empty($productIds)) {
                $productIdArray = explode(',', $productIds);
                $query->whereHas('products', function ($q) use ($productIdArray) {
                    $q->whereIn('product_id', $productIdArray);
                });
            }

            // Get total record count before applying limit
            $totalRecords = $query->count();

            // Order by latest quotation_date
            $query->orderBy('quotation_date', 'desc');

            $query->offset($offset)->limit($limit);

            // Fetch paginated results
            $get_quotations = $query->get();

            if ($get_quotations->isEmpty()) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No Quotations found!',
                ], 404);
            }

            // Transform Data for each quotation
            $get_quotations->transform(function ($quotation) {
                $quotation->quotation_date = $quotation->quotation_date_formatted;
                unset($quotation->quotation_date_formatted);
                $quotation->amount_in_words = $this->convertNumberToWords($quotation->total);
                $quotation->total = is_numeric($quotation->total)
                                    ? number_format((float)$quotation->total, 2)
                                    : $quotation->total;
                $quotation->user = $quotation->get_user 
                                    ? ['id' => $quotation->get_user->id, 'name' => $quotation->get_user->name]
                                    : ['id' => null, 'name' => 'Unknown'];
                $quotation->sales_person = $quotation->salesPerson 
                                    ? ['id' => $quotation->salesPerson->id, 'name' => $quotation->salesPerson->name]
                                    : ['id' => null, 'name' => 'Unknown'];
                $quotation->template = $quotation->get_template 
                                    ? ['id' => $quotation->get_template->id, 'name' => $quotation->get_template->name]
                                    : ['id' => null, 'name' => 'Unknown'];
                unset($quotation->get_user, $quotation->salesPerson, $quotation->get_template);

                // Transform client: Only return state from addresses
                if ($quotation->client) {
                    $state = optional($quotation->client->addresses->first())->state;
                    $quotation->client = ['state' => $state];
                } else {
                    $quotation->client = null;
                }

                // Transform terms: Replace term_master_id with a term_master object
                $quotation->terms = $quotation->terms->map(function ($term) {
                    return [
                        'quotation_id' => $term->quotation_id,
                        'name' => $term->name,
                        'value' => $term->value,
                        'term_master' => $term->termMaster 
                            ? [
                                'id'      => $term->termMaster->id,
                                'name'    => $term->termMaster->name,
                                'default' => $term->termMaster->default,
                                'type'    => $term->termMaster->type,
                            ]
                            : null,
                    ];
                });

                return $quotation;
            });

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Quotations fetched successfully!',
                'data' => $get_quotations,
                'count' => $get_quotations->count(),
                'total_records' => $totalRecords,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Update Quotations
    public function update_quotations(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'name' => 'nullable|string|exists:t_clients,name',
            'quotation_no' => 'nullable|string',
            'quotation_date' => 'required|date',
            'enquiry_no' => 'required|string',
            'enquiry_date' => 'required|date',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            'sales_person' => 'required|exists:users,id',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            // for products
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.amount' => 'required|numeric',
            'products.*.delivery' => 'nullable|string|max:255',
            'products.*.attachment' => 'nullable|string',

             // for addons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'nullable|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',

             // for terms
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string',
            'terms.*.value' => 'required|string',
            'terms.*.term_master_id' => 'required|integer|exists:t_quotation_term_masters,id',
        ]);

        $quotation = QuotationsModel::where('id', $id)->first();

        if (!$quotation) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Quotation not found!'], 404);
        }

        $quotationUpdated = $quotation->update([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name') !== null ? $request->input('name') : $quotation->name,
            'quotation_no' => $request->input('quotation_no'),
            'quotation_date' => $request->input('quotation_date'),
            'enquiry_no' => $request->input('enquiry_no'),
            'enquiry_date' => $request->input('enquiry_date'),
            'template' => $request->input('template'),
            'contact_person' =>$request->input('contact_person'),
            'sales_person' => $request->input('sales_person'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                    'discount' => $productData['discount'],
                    'discount_type' => $productData['discount_type'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                    'amount' => $productData['amount'],
                    'delivery' => $productData['delivery'],
                    'attachment' => $productData['attachment'],
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
                    'discount_type' => $productData['discount_type'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                    'amount' => $productData['amount'],
                    'delivery' => $productData['delivery'],
                    'attachment' => $productData['attachment'],
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
                    'hsn' => '99',
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
                    'hsn' => '99',
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
                    'term_master_id' => $termData['term_master_id'],
                ]);
            } else {
                QuotationTermsModel::create([
                    'quotation_id' => $id,
                    'company_id' => Auth::user()->company_id,
                    'name' => $termData['name'],
                    'value' => $termData['value'],
                    'term_master_id' => $termData['term_master_id'],
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
    // public function importQuotations()
    // {
    //     // Increase memory and execution time for large imports
    //     ini_set('max_execution_time', 300); // 5 minutes
    //     ini_set('memory_limit', '1024M');   // Increase memory limit

    //     // Clear old data before import
    //     QuotationsModel::truncate();
    //     QuotationProductsModel::truncate();
    //     QuotationAddonsModel::truncate();
    //     QuotationTermsModel::truncate();

    //     $url = 'https://expo.egsm.in/assets/custom/migrate/quotation.php';

    //     // Fetch data from the external URL
    //     try {
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
    //     }

    //     $batchSize = 10; // Optimal batch size

    //     // Batching setup
    //     $quotationsBatch = [];
    //     $productsBatch = [];
    //     $addonsBatch = [];
    //     $termsBatch = [];

    //     $missedRecords = [];
    //     // **1️⃣ Collect Quotation Data**
    //     foreach ($data as $record) {
    //         // Decode JSON fields
    //         $enquiryData = json_decode($record['enq_no_date'] ?? '{}', true);
    //         $taxData = json_decode($record['tax'] ?? '{}', true);

    //         // Get client data
    //         $client = ClientsModel::where('name', $record['Client'])->first();

    //         if (!$client) {
    //             Log::error("Client not found: " . ($record['Client'] ?? 'Unknown Client'));
    //             $missedRecords[] = [
    //                 'reason' => 'Client not found',
    //                 'record' => $record
    //             ];
    //             continue; // Skip this record and continue processing
    //         }

    //         $client_contact = ClientContactsModel::select('id')->where('customer_id', $client->customer_id ?? 0)->first();

    //         $statusMap = [
    //             0 => 'pending',
    //             1 => 'completed',
    //             2 => 'rejected'
    //         ];

    //         $igst = array_key_exists('igst', $taxData) ? (float)$taxData['igst'] : 0;
    //         $cgst = array_key_exists('cgst', $taxData) ? (float)$taxData['cgst'] : 0;
    //         $sgst = array_key_exists('sgst', $taxData) ? (float)$taxData['sgst'] : 0;

    //         // Prepare quotation data
    //         $quotationsBatch[] = [
    //             'company_id' => Auth::user()->company_id,
    //             'client_id' => $client->id,
    //             // 'client_contact_id' => $client_contact->id ?? null,
    //             'name' => $record['Client'],
    //             // 'address_line_1' => $client->address_line_1 ?? null,
    //             // 'address_line_2' => $client->address_line_2 ?? null,
    //             // 'city' => $client->city ?? null,
    //             // 'pincode' => $client->pincode ?? null,
    //             // 'state' => $client->state ?? null,
    //             // 'country' => $client->country ?? 'India',
    //             'quotation_no' => $record['quotation_no'],
    //             'quotation_date' => !empty($record['quotation_date']) 
    //                 ? date('Y-m-d', strtotime($record['quotation_date'])) 
    //                 : null,
    //             'status' => $statusMap[$record['Status']] ?? 'pending',
    //             'user' => Auth::user()->id,
    //             'enquiry_no' => $enquiryData['enquiry_no'] ?? null,
    //             'enquiry_date' => !empty($enquiryData['enquiry_date']) 
    //                 ? date('Y-m-d', strtotime($enquiryData['enquiry_date'])) 
    //                 : null,
    //             // 'discount' => is_numeric($record['discount']) ? (float) $record['discount'] : 0,
    //             'cgst' => $cgst,
    //             'sgst' => $sgst,
    //             'igst' => $igst,
    //             'total' => is_numeric($record['total']) ? (float) $record['total'] : 0,
    //             'currency' => $record['currency'] ?? 'INR',
    //             'template' => json_decode($record['template'], true)['id'] ?? '0',
    //             'created_at' => now(),
    //             'updated_at' => now()
    //         ];

    //     }

    //     // **2️⃣ Batch Insert Quotations & Fetch IDs**
    //     foreach (array_chunk($quotationsBatch, $batchSize) as $chunk) {
    //         QuotationsModel::insert($chunk);
    //     }

    //     // Fetch newly inserted IDs
    //     $quotationIds = QuotationsModel::whereIn('quotation_no', array_column($quotationsBatch, 'quotation_no'))
    //     ->pluck('id', 'quotation_no')
    //     ->toArray();


    //     // **3️⃣ Insert Related Products, Addons, and Terms**
    //     foreach ($data as $record) {
    //         $quotationId = $quotationIds[$record['quotation_no']] ?? null;
    //         if (!$quotationId) {
    //             continue;
    //         }

    //         // Decode JSON fields again inside the loop
    //         $itemsData = json_decode($record['items'] ?? '{}', true);
    //         $addonsData = json_decode($record['addons'] ?? '{}', true);
    //         $termsData = json_decode($record['Terms'] ?? '{}', true);

    //         // if (!is_array($addonsData) || !is_array($itemsData) || !is_array($termsData)) {
    //         //     continue;
    //         // }

    //         foreach ($itemsData['product'] as $index => $product) {
    //             // dd($itemsData['delivery'][$index]);
    //             // dd($itemsData['attachment'][$index]);
    //             $productsBatch[] = [
    //                 'quotation_id' => $quotationId,
    //                 'company_id' => Auth::user()->company_id,
    //                 'product_id' => $index + 1,
    //                 'product_name' => $itemsData['product'][$index] ?? 'Unnamed Product',
    //                 'description' => $itemsData['desc'][$index] ?? null,
    //                 'quantity' => is_numeric($itemsData['quantity'][$index]) ? (int)$itemsData['quantity'][$index] : 0,
    //                 'unit' => $itemsData['unit'][$index] ?? null,
    //                 'price' => is_numeric($itemsData['price'][$index]) ? (float)$itemsData['price'][$index] : 0,
    //                 // Calculate the amount
    //                 'amount' => (
    //                     (isset($itemsData['quantity'][$index]) ? (float) $itemsData['quantity'][$index] : 0.0) *
    //                     (
    //                         (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0) -
    //                         (
    //                             ((isset($itemsData['discount'][$index]) ? (float) $itemsData['discount'][$index] : 0.0) *
    //                             (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0)) / 100
    //                         )
    //                     )
    //                 ) + (
    //                     (isset($itemsData['cgst'][$index]) ? (float) $itemsData['cgst'][$index] : 0.0) +
    //                     (isset($itemsData['sgst'][$index]) ? (float) $itemsData['sgst'][$index] : 0.0) +
    //                     (isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0.0)
    //                 ),
    //                 'delivery' => isset($itemsData['delivery'][$index]) 
    //                     ? (is_array($itemsData['delivery'][$index]) 
    //                         ? (empty(array_filter($itemsData['delivery'][$index])) ? null : implode(', ', $itemsData['delivery'][$index])) 
    //                         : trim($itemsData['delivery'][$index])) 
    //                     : null,
    //                 'discount_type' => 'percentage',
    //                 'discount' => is_numeric($itemsData['discount'][$index]) ? (float)$itemsData['discount'][$index] : 0,
    //                 'hsn' => $itemsData['hsn'][$index] ?? null,
    //                 'tax' => is_numeric($itemsData['tax'][$index] ?? null) ? (float)$itemsData['tax'][$index] : 0,
    //                 'cgst' => is_numeric($itemsData['cgst'][$index] ?? null) ? (float)$itemsData['cgst'][$index] : 0,
    //                 'sgst' => is_numeric($itemsData['sgst'][$index] ?? null) ? (float)$itemsData['sgst'][$index] : 0,
    //                 'igst' => is_numeric($itemsData['igst'][$index] ?? null) ? (float)$itemsData['igst'][$index] : 0,
    //                 'attachment' => isset($itemsData['attachment'][$index]) && trim($itemsData['attachment'][$index]) !== ''
    //                     ? (is_array($itemsData['attachment'][$index]) 
    //                         ? (empty(array_filter($itemsData['attachment'][$index])) ? null : json_encode($itemsData['attachment'][$index])) 
    //                         : $itemsData['attachment'][$index]) 
    //                     : null,
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ];
    //         }

    //         foreach ($addonsData as $name => $values) {
    //             $addonsBatch[] = [
    //                 'quotation_id' => $quotationId,
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $name,
    //                 'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
    //                 'tax' => 18,
    //                 'hsn' => $values['hsn'] ?? null,
    //                 'cgst' => (float)($values['cgst'] ?? 0),
    //                 'sgst' => (float)($values['sgst'] ?? 0),
    //                 'igst' => (float)($values['igst'] ?? 0),
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ];
    //         }

    //         foreach ($termsData as $name => $value) {
    //             $termsBatch[] = [
    //                 'quotation_id' => $quotationId,
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $name,
    //                 'value' => $value,
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ];
    //         }
    //     }

    //     // **4️⃣ Batch Insert Products, Addons, and Terms**
    //     foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
    //         QuotationProductsModel::insert($chunk);
    //     }
    //     foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
    //         QuotationAddonsModel::insert($chunk);
    //     }
    //     foreach (array_chunk($termsBatch, $batchSize) as $chunk) {
    //         QuotationTermsModel::insert($chunk);
    //     }

    //     // **4️⃣ Batch Insert**
    //     // QuotationProductsModel::insert($productsBatch);
    //     // QuotationAddonsModel::insert($addonsBatch);
    //     // QuotationTermsModel::insert($termsBatch);

    //     return response()->json([
    //         'code' => 200, 
    //         'success' => true, 
    //         'message' => 'Import successful',
    //         'missed_records' => $missedRecords,
    //     ], 200);
    // }
    public function importQuotations()
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        // Clear old data
        QuotationsModel::truncate();
        QuotationProductsModel::truncate();
        QuotationAddonsModel::truncate();
        QuotationTermsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/quotation.php';

        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }
        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');
        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        $batchSize = 500;

        $quotationsBatch = [];
        $productsBatch   = [];
        $addonsBatch     = [];
        $termsBatch      = [];
        $missedRecords   = [];

        // Cache clients & products for faster lookup
        $productMap = ProductsModel::pluck('id', 'name')->toArray();

        // Status mapping
        $statusMap = [
            '0' => 'pending', 0 => 'pending',
            '1' => 'completed', 1 => 'completed',
            '2' => 'rejected', 2 => 'rejected',
        ];

        // 1) Parent: Quotations
        foreach ($data as $record) {
            $clientName = $record['Client'] ?? null;
            $client     = $clientName ? ClientsModel::where('name', $clientName)->first() : null;

            if (!$client) {
                Log::error("Client not found: " . ($clientName ?? 'Unknown Client'));
                $missedRecords[] = ['reason' => 'Client not found', 'record' => $record];
                continue;
            }

            $taxObj     = $record['tax'] ?? [];
            $addonsObj  = $record['addons'] ?? [];
            $tplObj     = $record['template'] ?? [];
            $enqObj     = $record['enq_no_date'] ?? [];
            $itemsArr   = $record['items'] ?? [];

            // Quotation gross: prefer total_gross -> sum item.gross -> compute
            $qGross = isset($record['total_gross']) ? round((float)$record['total_gross'], 2) : null;
            if ($qGross === null) {
                $tmp = 0.0;
                foreach ($itemsArr as $it) {
                    if (isset($it['gross']) && $it['gross'] !== '') {
                        $tmp += (float)$it['gross'];
                    } else {
                        $q = isset($it['quantity']) ? (float)$it['quantity'] : 0.0;
                        $p = isset($it['price'])    ? (float)$it['price']    : 0.0;
                        $discRaw = isset($it['discount']) && $it['discount'] !== '' ? (float)$it['discount'] : 0.0;
                        $disc    = round($discRaw, 2);
                        if ($disc < $discRaw) $disc += 0.01;
                        $tmp += $q * ($p - ($disc * $p) / 100);
                    }
                }
                $qGross = round($tmp, 2);
            }

            // Roundoff on parent
            $roundoff = isset($addonsObj['roundoff']) && $addonsObj['roundoff'] !== '' ? (float)$addonsObj['roundoff'] : 0.0;

            $quotationsBatch[] = [
                'company_id'     => Auth::user()->company_id,
                'client_id'      => $client->id,
                'name'           => $clientName,
                'quotation_no'   => $record['quotation_no'] ?? null,
                'quotation_date' => !empty($record['quotation_date']) && $record['quotation_date'] !== '0000-00-00'
                                    ? date('Y-m-d', strtotime($record['quotation_date'])) : null,
                'status'         => $statusMap[$record['Status'] ?? '0'] ?? 'pending',
                'user'           => Auth::user()->id,
                'enquiry_no'     => $enqObj['enquiry_no']  ?? null,
                'enquiry_date'   => !empty($enqObj['enquiry_date']) && $enqObj['enquiry_date'] !== '0000-00-00'
                                    ? date('Y-m-d', strtotime($enqObj['enquiry_date'])) : null,
                'cgst'           => isset($taxObj['cgst']) ? (float)$taxObj['cgst'] : 0.0,
                'sgst'           => isset($taxObj['sgst']) ? (float)$taxObj['sgst'] : 0.0,
                'igst'           => isset($taxObj['igst']) ? (float)$taxObj['igst'] : 0.0,
                'total'          => isset($record['total']) ? round((float)$record['total'], 2) : 0.0,
                'currency'       => $record['currency'] ?? 'INR',
                'template'       => isset($tplObj['id']) ? (int)$tplObj['id'] : 0,
                'gross'          => $qGross,
                'round_off'      => round($roundoff, 2),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            if (count($quotationsBatch) >= $batchSize) {
                QuotationsModel::insert($quotationsBatch);
                $quotationsBatch = [];
            }
        }

        if (!empty($quotationsBatch)) {
            QuotationsModel::insert($quotationsBatch);
        }

        // Map quotation_no -> id
        $quotationIds = QuotationsModel::whereIn('quotation_no',
            array_map(fn($r) => $r['quotation_no'] ?? null, $data)
        )->pluck('id', 'quotation_no')->toArray();

        // 2) Children: Products, Addons, Terms
        foreach ($data as $record) {
            $qNo = $record['quotation_no'] ?? null;
            $qId = $qNo ? ($quotationIds[$qNo] ?? null) : null;
            if (!$qId) continue;

            $itemsArr  = $record['items'] ?? [];
            $addonsObj = $record['addons'] ?? [];
            $termsObj  = $record['Terms'] ?? [];

            // PRODUCTS (array of objects) — include gross
            foreach ($itemsArr as $idx => $it) {
                $productName = $it['product'] ?? null;
                if (!$productName) continue;

                $productId = $productMap[$productName] ?? 0; // set to 0 if not found (or handle as you prefer)

                $qty   = isset($it['quantity']) ? (float)$it['quantity'] : 0.0;
                $price = isset($it['price'])    ? (float)$it['price']    : 0.0;

                $discRaw = isset($it['discount']) && $it['discount'] !== '' ? (float)$it['discount'] : 0.0;
                $disc    = round($discRaw, 2);
                if ($disc < $discRaw) $disc += 0.01;

                // line gross → prefer API
                if (isset($it['gross']) && $it['gross'] !== '') {
                    $lineGross = round((float)$it['gross'], 2);
                } else {
                    $lineGross = round($qty * ($price - ($disc * $price) / 100), 2);
                }

                $lineCgst = isset($it['cgst']) ? (float)$it['cgst'] : 0.0;
                $lineSgst = isset($it['sgst']) ? (float)$it['sgst'] : 0.0;
                $lineIgst = isset($it['igst']) ? (float)$it['igst'] : 0.0;

                $lineAmount = round($lineGross + $lineCgst + $lineSgst + $lineIgst, 2);

                $productsBatch[] = [
                    'quotation_id'   => $qId,
                    'company_id'     => Auth::user()->company_id,
                    'product_id'     => $productId,
                    'product_name'   => $productName,
                    'description'    => $it['desc'] ?? null,
                    'quantity'       => $qty,
                    'unit'           => $it['unit'] ?? null,
                    'price'          => $price,
                    'discount_type'  => 'percentage',
                    'discount'       => $disc,
                    'hsn'            => $it['hsn'] ?? null,
                    'tax'            => isset($it['tax']) ? (float)$it['tax'] : 0.0,
                    'cgst'           => $lineCgst,
                    'sgst'           => $lineSgst,
                    'igst'           => $lineIgst,
                    'gross'          => $lineGross,   // <-- required
                    'amount'         => $lineAmount,
                    'delivery'       => isset($it['delivery']) ? (is_array($it['delivery'])
                                            ? (empty(array_filter($it['delivery'])) ? null : implode(', ', $it['delivery']))
                                            : trim((string)$it['delivery'])) : null,
                    'attachment'     => isset($it['attachment']) && trim((string)$it['attachment']) !== ''
                                            ? (is_array($it['attachment'])
                                                ? (empty(array_filter($it['attachment'])) ? null : json_encode($it['attachment']))
                                                : (string)$it['attachment'])
                                            : null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];

                if (count($productsBatch) >= $batchSize) {
                    QuotationProductsModel::insert($productsBatch);
                    $productsBatch = [];
                }
            }

            // ADDONS (skip roundoff as a row)
            if (!empty($addonsObj)) {
                foreach ($addonsObj as $name => $values) {
                    if (strtolower($name) === 'roundoff') continue;

                    $valCgst = is_array($values) && array_key_exists('cgst', $values) ? (float)$values['cgst'] : 0.0;
                    $valSgst = is_array($values) && array_key_exists('sgst', $values) ? (float)$values['sgst'] : 0.0;
                    $valIgst = is_array($values) && array_key_exists('igst', $values) ? (float)$values['igst'] : 0.0;
                    $valHsn  = is_array($values) && array_key_exists('hsn',  $values) ? (string)$values['hsn'] : null;

                    $addonsBatch[] = [
                        'quotation_id' => $qId,
                        'company_id'   => Auth::user()->company_id,
                        'name'         => $name,
                        'amount'       => round($valCgst + $valSgst + $valIgst, 2),
                        'tax'          => 18,
                        'hsn'          => $valHsn,
                        'cgst'         => $valCgst,
                        'sgst'         => $valSgst,
                        'igst'         => $valIgst,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];

                    if (count($addonsBatch) >= $batchSize) {
                        QuotationAddonsModel::insert($addonsBatch);
                        $addonsBatch = [];
                    }
                }
            }

            // TERMS
            if (!empty($termsObj) && is_array($termsObj)) {
                foreach ($termsObj as $name => $value) {
                    $termsBatch[] = [
                        'quotation_id' => $qId,
                        'company_id'   => Auth::user()->company_id,
                        'name'         => $name,
                        'value'        => $value !== '' ? $value : null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];

                    if (count($termsBatch) >= $batchSize) {
                        QuotationTermsModel::insert($termsBatch);
                        $termsBatch = [];
                    }
                }
            }
        }

        // Flush remainders
        if (!empty($productsBatch)) QuotationProductsModel::insert($productsBatch);
        if (!empty($addonsBatch))   QuotationAddonsModel::insert($addonsBatch);
        if (!empty($termsBatch))    QuotationTermsModel::insert($termsBatch);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Import successful',
            'missed_records' => $missedRecords,
        ], 200);
    }

    // Excel Export
    public function exportQuotationReport(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // === Base query (same structure as view_quotations) ===
            $query = QuotationsModel::with([
                'get_user:id,name',
                'get_template:id,name',
                'salesPerson:id,name',
                'contactPerson:id,name', // <-- make sure relation exists in model
                'client' => function ($q) {
                    $q->select('id', 'customer_id')
                    ->with(['addresses' => function ($query) {
                        $query->select('customer_id', 'state');
                    }]);
                },
            ])
            ->select(
                'id',
                'client_id',
                'name',
                'quotation_no',
                'quotation_date',
                DB::raw('DATE_FORMAT(quotation_date, "%d-%m-%Y") as quotation_date_formatted'),
                'enquiry_no',
                DB::raw('DATE_FORMAT(enquiry_date, "%d-%m-%Y") as enquiry_date_formatted'),
                'template',
                'contact_person',
                'sales_person',
                'status',
                'user',
                'cgst',
                'sgst',
                'igst',
                'total',
                'currency',
                'gross',
                'round_off',
                'created_at'
            )
            ->where('company_id', $companyId);

            // ================= MULTI-VALUE FILTER PARSING ==================

            $clientIds        = $request->filled('client_id') 
                                ? array_map('intval', explode(',', $request->client_id)) 
                                : null;

            $quotationNos     = $request->filled('quotation_no') 
                                ? explode(',', $request->quotation_no) 
                                : null;

            $enquiryNos       = $request->filled('enquiry_no') 
                                ? explode(',', $request->enquiry_no) 
                                : null;

            $contactPersonIds = $request->filled('client_contact_id') 
                                ? array_map('intval', explode(',', $request->client_contact_id)) 
                                : null;

            $name            = $request->input('name');          // <<=== NEW
            $userId           = $request->input('user');
            $status           = $request->input('status'); // single
            $dateFrom         = $request->input('date_from');
            $dateTo           = $request->input('date_to');
            $quotationDate    = $request->input('quotation_date');
            $enquiryDate      = $request->input('enquiry_date');
            $limit            = (int) $request->input('limit', 0);
            $offset           = (int) $request->input('offset', 0);

            // ================= APPLY FILTERS ==================

            // Multi client_id
            if ($clientIds) {
                $query->whereIn('client_id', $clientIds);
            }
            // Name (party/client name) - LIKE search
            if (!empty($name)) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            }
            
            // Multi quotation_no
            if ($quotationNos) {
                $query->where(function ($q) use ($quotationNos) {
                    foreach ($quotationNos as $no) {
                        $q->orWhere('quotation_no', 'LIKE', '%' . trim($no) . '%');
                    }
                });
            }

            // Multi enquiry_no
            if ($enquiryNos) {
                $query->where(function ($q) use ($enquiryNos) {
                    foreach ($enquiryNos as $no) {
                        $q->orWhere('enquiry_no', 'LIKE', '%' . trim($no) . '%');
                    }
                });
            }

            // Multi client contact person ID
            if ($contactPersonIds) {
                $query->whereIn('contact_person', $contactPersonIds);
            }

            // Date filters
            if ($dateFrom && $dateTo) {
                $query->whereBetween('quotation_date', [$dateFrom, $dateTo]);
            } elseif ($dateFrom) {
                $query->whereDate('quotation_date', '>=', $dateFrom);
            } elseif ($dateTo) {
                $query->whereDate('quotation_date', '<=', $dateTo);
            }

            // Single exact quotation date
            if ($quotationDate) {
                $query->whereDate('quotation_date', $quotationDate);
            }

            // Single exact enquiry date
            if ($enquiryDate) {
                $query->whereDate('enquiry_date', $enquiryDate);
            }

            // User
            if ($userId) {
                $query->where('user', $userId);
            }

            // Status
            if (!empty($status)) {
                $query->where('status', $status);
            }

            // Order & pagination (limit/offset)
            $query->orderBy('quotation_date', 'desc');

            if ($offset > 0) {
                $query->offset($offset);
            }
            if ($limit > 0) {
                $query->limit($limit);
            }

            // Fetch filtered data
            $quotations = $query->get();

            if ($quotations->isEmpty()) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'No quotations found for export!'
                ]);
            }

            // ========================================================
            //             BUILD EXPORT DATA (HEADER LEVEL)
            // ========================================================

            $exportData = [];
            $sn = 1;

            foreach ($quotations as $q) {
                $state = optional($q->client->addresses->first())->state;

                $exportData[] = [
                    'SN'              => $sn++,
                    'Quotation No'    => $q->quotation_no,
                    'Quotation Date'  => $q->quotation_date_formatted,
                    'Enquiry No'      => $q->enquiry_no,
                    'Enquiry Date'    => $q->enquiry_date_formatted,
                    'Client Name'     => $q->name,
                    'Client State'    => $state,
                    'Currency'        => $q->currency,
                    'Gross'           => $q->gross,
                    'CGST'            => $q->cgst,
                    'SGST'            => $q->sgst,
                    'IGST'            => $q->igst,
                    'Total'           => $q->total,
                    'Amount In Words' => $this->convertNumberToWords($q->total),
                    'Status'          => $q->status,
                    'User'            => $q->get_user->name        ?? 'Unknown',
                    'Sales Person'    => $q->salesPerson->name     ?? 'Unknown',
                    'Contact Person'  => $q->contactPerson->name   ?? 'Unknown', // ← added
                    'Template'        => $q->get_template->name    ?? 'Unknown',
                    'Created At'      => $q->created_at
                                            ? Carbon::parse($q->created_at)->format('d-m-Y H:i')
                                            : null,
                ];
            }

            // ========================================================
            //                EXPORT TO EXCEL FILE
            // ========================================================

            $timestamp    = now()->format('Ymd_His');
            $fileName     = "quotations_export_{$timestamp}.xlsx";
            $relativePath = "uploads/quotation_report/{$fileName}";

            Excel::store(
                new class($exportData) implements FromCollection, WithHeadings {
                    private $data;

                    public function __construct($data)
                    {
                        $this->data = $data;
                    }

                    public function collection()
                    {
                        return collect($this->data);
                    }

                    public function headings(): array
                    {
                        return [
                            'SN',
                            'Quotation No',
                            'Quotation Date',
                            'Enquiry No',
                            'Enquiry Date',
                            'Client Name',
                            'Client State',
                            'Currency',
                            'Gross',
                            'CGST',
                            'SGST',
                            'IGST',
                            'Total',
                            'Amount In Words',
                            'Status',
                            'User',
                            'Sales Person',
                            'Contact Person',
                            'Template',
                            'Created At'
                        ];
                    }
                },
                $relativePath,
                'public'
            );

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Quotation report generated successfully!',
                'data'    => [
                    'file_url'     => asset("storage/{$relativePath}"),
                    'file_name'    => $fileName,
                    'file_size'    => Storage::disk('public')->size($relativePath),
                    'content_type' => 'Excel'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Error exporting quotations.',
                'error'   => $e->getMessage()
            ], 500);
        }
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

    public function generateQuotationPDF($id)
    {
        // Retrieve the quotation with its relations
        $quotation = QuotationsModel::with(['products', 'addons'])->findOrFail($id);

        // ---------- 1. ITEMS: from t_quotation_products ----------
        $products = $quotation->products;

        $items = [];
        foreach ($products as $product) {
            $items[] = [
                'desc'     => $product->description ?? '',
                'make'     => $product->product_name ?? '',
                'hsn'      => $product->hsn ?? '',
                'qty'      => $product->quantity ?? 0,
                'unit'     => $product->unit ?? '',
                'rate'     => $product->price ?? 0,
                'delivery' => $product->delivery ?? '',
                'disc'     => $product->discount ?? 0,
                'tax'      => $product->tax ?? 0,
                'cgst'     => $product->cgst ?? 0,
                'sgst'     => $product->sgst ?? 0,
                'igst'     => $product->igst ?? 0,
                'amount'   => $product->amount ?? 0,
            ];
        }

        // ---------- 2. SPLIT ITEMS INTO PAGES (your custom rules) ----------
        $itemPages = $this->chunkItemsForPages($items);

        // ---------- 3. TAX SUMMARY ----------
        $tax_summary = [];
        $grouped = $products->groupBy('hsn');

        foreach ($grouped as $hsn => $groupItems) {
            $cgstSum = $groupItems->sum('cgst');
            $sgstSum = $groupItems->sum('sgst');
            $igstSum = $groupItems->sum('igst');

            $totalTax = $cgstSum + $sgstSum + $igstSum;

            $taxableSum = $groupItems->sum(function ($item) {
                return ($item->amount ?? 0) - ($item->cgst ?? 0) - ($item->sgst ?? 0) - ($item->igst ?? 0);
            });

            $avgTaxRate = $groupItems->avg('tax') ?? 0;

            $tax_summary[] = [
                'hsn'        => $hsn,
                'rate'       => round($avgTaxRate, 2),
                'taxable'    => round($taxableSum, 2),
                'cgst'       => round($cgstSum, 2),
                'sgst'       => round($sgstSum, 2),
                'igst'       => round($igstSum, 2),
                'total_tax'  => round($totalTax, 2),
            ];
        }

        // ---------- 4. ADDONS: PF & FREIGHT ----------
        $addons = $quotation->addons;

        $pfAmount = $addons
            ->filter(fn($a) => strtolower(trim($a->name)) === 'pf')
            ->sum('amount');

        $freightAmount = $addons
            ->filter(fn($a) => strtolower(trim($a->name)) === 'freight')
            ->sum('amount');

        // ---------- 5. TAX STYLE: IGST or CGST+SGST ----------
        $igstTotal = $quotation->igst ?? 0;
        $cgstTotal = $quotation->cgst ?? 0;
        $sgstTotal = $quotation->sgst ?? 0;

        $showIgst = $igstTotal > 0;
        $showCgstSgst = !$showIgst;

        // ---------- 6. Prepare data for Blade ----------
        $data = [
            'quotation_no'      => $quotation->quotation_no ?? '',
            'quotation_date'    => $quotation->quotation_date ?? '',
            'enquiry_no'        => $quotation->enquiry_no ?? '',
            'enquiry_date'      => $quotation->enquiry_date ?? '',

            'gross_total'       => $quotation->gross ?? 0,
            'cgst'              => $cgstTotal,
            'sgst'              => $sgstTotal,
            'igst'              => $igstTotal,
            'roundoff'          => $quotation->round_off ?? 0,

            'grand_total'       => $quotation->total ?? 0,
            'grand_total_words' => $this->convertNumberToWords($quotation->total ?? 0),

            'itemPages'         => $itemPages,  // <-- NEW PAGINATED ITEMS
            'tax_summary'       => $tax_summary,

            'pf_amount'         => $pfAmount,
            'freight_amount'    => $freightAmount,

            'show_igst'         => $showIgst,
            'show_cgst_sgst'    => $showCgstSgst,
        ];

        // ---------- 7. Generate PDF ----------
        $pdf = new \Mpdf\Mpdf([
            'format'        => 'A4',
            'margin_top'    => 55,   // space for header
            'margin_bottom' => 90,   // space for footer + summary
            'margin_left'   => 10,
            'margin_right'  => 10,
        ]);

        $html = view('quotation.pdf', $data)->render();
        $pdf->WriteHTML($html);

        return $pdf->Output('quotation.pdf', 'I');
    }

    private function chunkItemsForPages(array $items): array
    {
        $fullCap = 13;  // max items on a normal page
        $lastCap = 8;   // max items on the page that also has summary

        $total  = count($items);
        $pages  = [];

        // Case 1: everything fits on one page with summary
        if ($total <= $lastCap) {
            $pages[] = $items;
            return $pages;
        }

        $remaining = $total;
        $offset    = 0;

        // Case 2: we need more than 2 pages
        // While items remaining > 13 + 8 = 21, keep adding full pages of 13
        while ($remaining > ($fullCap + $lastCap)) { // >21
            $pages[]   = array_slice($items, $offset, $fullCap);
            $offset   += $fullCap;
            $remaining -= $fullCap;
        }

        // Now remaining is between 9 and 21 (or <=8)

        // If remaining can fit on a single summary page (unlikely here but safe)
        if ($remaining <= $lastCap) {
            $pages[] = array_slice($items, $offset, $remaining);
        } else {
            // We must split remaining into:
            //   - one normal page (<=13)
            //   - one summary page (<=8)

            if ($remaining - $fullCap >= 1 && $remaining - $fullCap <= $lastCap) {
                // e.g. 14 => 13 + 1, 18 => 13 + 5, 17 => 13 + 4, etc.
                $pages[] = array_slice($items, $offset, $fullCap);
                $offset += $fullCap;
                $pages[] = array_slice($items, $offset, $remaining - $fullCap);
            } else {
                // Special small cases like 9 or 10:
                // 9  => 8 + 1
                // 10 => 8 + 2
                $firstPageCount = 8;
                $pages[] = array_slice($items, $offset, $firstPageCount);
                $offset += $firstPageCount;
                $pages[] = array_slice($items, $offset, $remaining - $firstPageCount);
            }
        }

        return $pages;
    }

    // public function generateQuotationPDF($id)
    // {
    //     // Retrieve the quotation with its relations
    //     $quotation = QuotationsModel::with(['products', 'addons'])->findOrFail($id);

    //     // ---------- 1. ITEMS: from t_quotation_products ----------
    //     $products = $quotation->products; // Eloquent collection

    //     $items = [];
    //     foreach ($products as $product) {
    //         $items[] = [
    //             'desc'     => $product->description ?? '',
    //             'make'     => $product->product_name ?? '',
    //             'hsn'      => $product->hsn ?? '',
    //             'qty'      => $product->quantity ?? 0,
    //             'unit'     => $product->unit ?? '',
    //             'rate'     => $product->price ?? 0,
    //             'delivery' => $product->delivery ?? '',
    //             'disc'     => $product->discount ?? 0,
    //             'tax'      => $product->tax ?? 0,
    //             'cgst'     => $product->cgst ?? 0,
    //             'sgst'     => $product->sgst ?? 0,
    //             'igst'     => $product->igst ?? 0,   // <--- add igst here
    //             'amount'   => $product->amount ?? 0,
    //         ];
    //     }

    //     // ---------- 2. TAX SUMMARY: grouped by HSN from products ----------
    //     $tax_summary = [];
    //     $grouped = $products->groupBy('hsn');

    //     foreach ($grouped as $hsn => $groupItems) {
    //         $cgstSum = $groupItems->sum('cgst');
    //         $sgstSum = $groupItems->sum('sgst');
    //         $igstSum = $groupItems->sum('igst');

    //         $totalTax = $cgstSum + $sgstSum + $igstSum;

    //         // taxable = line amount - all taxes
    //         $taxableSum = $groupItems->sum(function ($item) {
    //             $amount = $item->amount ?? 0;
    //             $cgst   = $item->cgst ?? 0;
    //             $sgst   = $item->sgst ?? 0;
    //             $igst   = $item->igst ?? 0;

    //             return $amount - $cgst - $sgst - $igst;
    //         });

    //         $avgTaxRate = $groupItems->avg('tax') ?? 0;

    //         $tax_summary[] = [
    //             'hsn'        => $hsn,
    //             'rate'       => round($avgTaxRate, 2),
    //             'taxable'    => round($taxableSum, 2),
    //             'cgst'       => round($cgstSum, 2),
    //             'sgst'       => round($sgstSum, 2),
    //             'igst'       => round($igstSum, 2),     // <--- keep igst separately
    //             'total_tax'  => round($totalTax, 2),
    //         ];
    //     }

    //     // ---------- 3. ADD-ONS: PF & FREIGHT from t_quotation_addons ----------
    //     $addons = $quotation->addons; // collection of QuotationAddonsModel

    //     // Match 'pf' and 'freight' (case-insensitive, trims spaces)
    //     $pfAmount = $addons
    //         ->filter(function ($addon) {
    //             return strtolower(trim($addon->name)) === 'pf';
    //         })
    //         ->sum('amount');

    //     $freightAmount = $addons
    //         ->filter(function ($addon) {
    //             return strtolower(trim($addon->name)) === 'freight';
    //         })
    //         ->sum('amount');


    //     // ---------- 4. Decide which tax style to show (IGST vs CGST+SGST) ----------
    //     $igstTotal = $quotation->igst ?? 0;
    //     $cgstTotal = $quotation->cgst ?? 0;
    //     $sgstTotal = $quotation->sgst ?? 0;

    //     $showIgst      = $igstTotal > 0;            // if IGST present, don't show CGST/SGST block
    //     $showCgstSgst  = !$showIgst;                // otherwise use CGST+SGST

    //     // ---------- 5. Build data array for the view ----------
    //     $data = [
    //         'quotation_no'      => $quotation->quotation_no ?? '',
    //         'quotation_date'    => $quotation->quotation_date ?? '',
    //         'enquiry_no'        => $quotation->enquiry_no ?? '',
    //         'enquiry_date'      => $quotation->enquiry_date ?? '',

    //         'gross_total'       => $quotation->gross ?? 0,
    //         'cgst'              => $cgstTotal,
    //         'sgst'              => $sgstTotal,
    //         'igst'              => $igstTotal,
    //         'roundoff'          => $quotation->round_off ?? 0, // <--- matches DB column

    //         'grand_total'       => $quotation->total ?? 0,
    //         'grand_total_words' => $this->convertNumberToWords($quotation->total ?? 0),

    //         // Items and tax summary
    //         'items'             => $items,
    //         'tax_summary'       => $tax_summary,

    //         // Add-ons
    //         'pf_amount'         => $pfAmount,
    //         'freight_amount'    => $freightAmount,

    //         // Tax style flags
    //         'show_igst'         => $showIgst,
    //         'show_cgst_sgst'    => $showCgstSgst,
    //     ];

    //     // ---------- 6. Generate PDF ----------
    //     // $pdf = new \Mpdf\Mpdf([
    //     //     'format'        => 'A4',
    //     //     'margin_top'    => 5,
    //     //     'margin_bottom' => 5,
    //     //     'margin_left'   => 5,
    //     //     'margin_right'  => 5,
    //     // ]);
    //     $pdf = new \Mpdf\Mpdf([
    //         'format'        => 'A4',

    //         // space for header (logo + title + dashed line)
    //         'margin_top'    => 55,   // you can tweak 50–60 if needed

    //         // space for footer (bank details + T&C + footer image)
    //         'margin_bottom' => 90,   // increased so content never enters footer area

    //         'margin_left'   => 10,
    //         'margin_right'  => 10,
    //     ]);


    //     $html = view('quotation.pdf', $data)->render();
    //     $pdf->WriteHTML($html);

    //     return $pdf->Output('quotation.layout', 'I');
    // }

    public function fetchQuotationsAllProducts(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Inputs
            $limit = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $sortField = $request->input('sort_field', 'quotation_date');
            $sortOrder = strtolower($request->input('sort_order', 'asc'));
            $search = $request->input('search');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Valid sortable fields - adjust as needed
            $validFields = ['quotation_no', 'quotation_date', 'client', 'qty', 'price', 'amount'];

            if (!in_array($sortField, $validFields)) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invalid sort field.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0,
                ], 422);
            }

            // Base query
            $query = QuotationProductsModel::with([
                'quotation:id,quotation_no,quotation_date,client_id',
                'quotation.client:id,name',
                'product:id,name',
            ])->where('company_id', $companyId);

            // Date filter on quotation_date in quotation
            if ($startDate || $endDate) {
                $query->whereHas('quotation', function ($q) use ($startDate, $endDate) {
                    if ($startDate) {
                        $q->where('quotation_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->where('quotation_date', '<=', $endDate);
                    }
                });
            }

            // Get data and transform
            $records = $query->select('quotation_id', 'product_id', 'quantity', 'price', 'amount')
                ->get()
                ->map(function ($item) {
                    return [
                        'quotation_no' => optional($item->quotation)->quotation_no,
                        'quotation_date' => optional($item->quotation)->quotation_date,
                        'client' => optional($item->quotation->client)->name,
                        'product_name' => optional($item->product)->name,
                        'qty' => (float) $item->quantity,
                        'price' => (float) $item->price,
                        'amount' => (float) $item->amount,
                    ];
                })->toArray();

            // Filter by search (quotation_no or client)
            if (!empty($search)) {
                $records = array_filter($records, function ($item) use ($search) {
                    return stripos($item['quotation_no'], $search) !== false ||
                        stripos($item['client'], $search) !== false;
                });
            }

            // Sort results
            usort($records, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc' ? $a[$sortField] <=> $b[$sortField] : $b[$sortField] <=> $a[$sortField];
            });

            $totalRecords = count($records);

            // Pagination
            $paginated = array_slice($records, $offset, $limit);

            // Totals
            $totalQty = array_sum(array_column($records, 'qty'));
            $totalAmount = array_sum(array_column($records, 'amount'));
            $subQty = array_sum(array_column($paginated, 'qty'));
            $subAmount = array_sum(array_column($paginated, 'amount'));

            $subTotalRow = [
                'quotation_no' => '',
                'quotation_date' => '',
                'client' => 'SubTotal -',
                'product_name' => '',
                'qty' => $subQty,
                'price' => '',
                'amount' => $subAmount,
            ];

            $totalRow = [
                'quotation_no' => '',
                'quotation_date' => '',
                'client' => 'Total -',
                'product_name' => '',
                'qty' => $totalQty,
                'price' => '',
                'amount' => $totalAmount,
            ];

            $paginated[] = $subTotalRow;
            $paginated[] = $totalRow;

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error fetching quotations: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0,
            ], 500);
        }
    }

}
