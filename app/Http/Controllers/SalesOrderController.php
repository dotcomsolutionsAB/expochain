<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\SalesOrderAddonsModel;
use App\Models\ClientsModel;
use App\Models\ClientContactsModel;
use App\Models\ClientAddressModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use App\Models\CounterModel;
use Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Storage;
use DB;
use NumberFormatter;

class SalesOrderController extends Controller
{
    //
    // create
    public function add_sales_order(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'sales_order_no' => 'required|string|unique:t_sales_order,sales_order_no',
            'sales_order_date' => 'required|date_format:Y-m-d',
            'ref_no' => 'required|string',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'sales_person' => 'required|integer|exists:users,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',
        
            // for products 
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit' => 'required|string|max:20',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'nullable|string',
            'products.*.tax' => 'nullable|numeric|min:0',
            'products.*.cgst' => 'nullable|numeric|min:0',
            'products.*.sgst' => 'nullable|numeric|min:0',
            'products.*.igst' => 'nullable|numeric|min:0',
            'products.*.amount' => 'nullable|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',
            
            // for add-ons 
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0'        
        ]);

        // Fetch the client details using client_id
        $client = ClientsModel::find($request->input('client_id'));

        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'sales_order',
            'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $sales_order_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $sales_order_no = $request->input('sales_order_no');
        }

        $exists = SalesOrderModel::where('company_id', Auth::user()->company_id)
            ->where('sales_order_no', $sales_order_no)
            ->exists();

        if ($exists) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'error' => 'The combination of company_id and sales_order_no must be unique.',
            ], 422);
        }

        // Register the sales order
        $register_sales_order = SalesOrderModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $request->input('sales_order_date'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'sales_person' => $request->input('sales_person'),
            'status' => "pending",
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
        ]);

         // **Step 2: Register Sales Order Products**
        $products = $validatedData['products'];
        foreach ($products as $product) {
            // Create a record for the product
            SalesOrderProductsModel::create([
                'sales_order_id' => $register_sales_order->id,
                'company_id' => Auth::user()->company_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'quantity' => $product['quantity'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'discount' => $product['discount'],
                'discount_type' => $product['discount_type'],
                'hsn' => $product['hsn'],
                'tax' => $product['tax'],
                'cgst' =>$product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
                'amount' => $product['amount'],
                'channel' => $product['channel'],
            ]);
        }

        // Process and insert addons
        // **Step 3: Register Sales Order Add-ons**
        $addons = $validatedData['addons'] ?? [];
        foreach ($addons as $addon) {
            SalesOrderAddonsModel::create([
                'sales_order_id' => $register_sales_order->id,
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

        // increment the `next_number` by 1
        CounterModel::where('name', 'sales_order')
            ->where('company_id', Auth::user()->company_id)
            ->increment('next_number');

        unset($register_sales_order['id'], $register_sales_order['created_at'], $register_sales_order['updated_at']);

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Sales Order registered successfully!',
            'data' => $register_sales_order,
        ], 201);
    }

    // View Sales Orders
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }

    public function view_sales_order(Request $request, $id = null)
    {
        // Get filter inputs
        $clientId = $request->input('client_id');
        $clientContactId = $request->input('client_contact_id');
        $name = $request->input('name');
        $salesOrderNo = $request->input('sales_order_no');
        $salesOrderDate = $request->input('sales_order_date');
        $user = $request->input('user'); 
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->input('status');
        $productIds = $request->input('product_ids'); 
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        // Query Sales Orders
        // $query = SalesOrderModel::with([
        //     'client:id, customer_id, name,mobile,email,gstin', // Fetch client details
        //     'clientAddress:customer_id as customer,country,address_line_1,address_line_2,city,state,pincode', // Fetch client address
        //     'products' => function ($query) {
        //         $query->select(
        //             'sales_order_id', 'product_id', 'product_name', 'description',
        //             'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 
        //             DB::raw('(tax / 2) as cgst_rate'), 
        //             DB::raw('(tax / 2) as sgst_rate'), 
        //             DB::raw('(tax) as igst_rate'), 
        //             'amount', 'channel', 'sent', 'short_closed'
        //         )->with(['channel' => function ($channelQuery) {
        //             $channelQuery->select('id', 'name');
        //         }]);
        //     },
        //     'addons' => function ($query) {
        //         $query->select('sales_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
        //     },
        //     'get_user:id,name'
        // ])
        // ->select('id', 'client_id', 'name', 'sales_order_no', 
        //     DB::raw('DATE_FORMAT(sales_order_date, "%d-%m-%Y") as sales_order_date'), 
        //     'ref_no', 'template', 'contact_person', 'status', 'user', 'cgst', 'sgst', 'igst', 'total', 'gross', 'round_off'
        // )
        // ->where('company_id', Auth::user()->company_id);

        $query = SalesOrderModel::with([
            'client:id,customer_id,name,mobile,email,gstin', // fetch client details
            'clientAddress', // let the relationship define its columns (with aliasing)
            'products' => function ($query) {
                $query->select(
                    'sales_order_id', 'product_id', 'product_name', 'description',
                    'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 
                    DB::raw('(tax / 2) as cgst_rate'), 
                    DB::raw('(tax / 2) as sgst_rate'), 
                    DB::raw('(tax) as igst_rate'), 
                    'amount', 'channel', 'sent', 'short_closed'
                )->with(['channel' => function ($channelQuery) {
                    $channelQuery->select('id', 'name');
                }]);
            },
            'addons' => function ($query) {
                $query->select('sales_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            },
            'get_user:id,name'
        ])
        ->select('id', 'client_id', 'name', 'sales_order_no', 
            DB::raw('DATE_FORMAT(sales_order_date, "%d-%m-%Y") as sales_order_date'), 
            'ref_no', 'template', 'sales_person', 'status', 'user', 'cgst', 'sgst', 'igst', 'total', 'gross', 'round_off'
        )
        ->where('company_id', Auth::user()->company_id);
        

        // 🔹 **Fetch Single Sales Order by ID**
        if ($id) {
            $salesOrder = $query->where('id', $id)->first();
            if (!$salesOrder) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Sales Order not found!',
                ], 404);
            }

            // Transform Single Sales Order
            $salesOrder->amount_in_words = $this->convertNumberToWords($salesOrder->total);
            $salesOrder->total = is_numeric($salesOrder->total) ? number_format((float) $salesOrder->total, 2) : $salesOrder->total;
            $salesOrder->sales_person = $salesOrder->get_user ? ['id' => $salesOrder->get_user->id, 'name' => $salesOrder->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            $salesOrder->user = $salesOrder->get_user ? ['id' => $salesOrder->get_user->id, 'name' => $salesOrder->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            unset($salesOrder->get_user);

            // Add client details
            $salesOrder->client = $salesOrder->client ? [
                'name' => $salesOrder->client->name,
                'mobile' => $salesOrder->client->mobile,
                'email' => $salesOrder->client->email,
                'gstin' => $salesOrder->client->gstin
            ] : null;

             // Add client address details
            $salesOrder->client_address = $salesOrder->clientAddress ? [
                'country' => $salesOrder->clientAddress->country,
                'address_line_1' => $salesOrder->clientAddress->address_line_1,
                'address_line_2' => $salesOrder->clientAddress->address_line_2,
                'city' => $salesOrder->clientAddress->city,
                'state' => $salesOrder->clientAddress->state,
                'pincode' => $salesOrder->clientAddress->pincode
            ] : null;

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Sales Order fetched successfully!',
                'data' => $salesOrder,
            ], 200);
        }

        // 🔹 **Apply Filters for Listing**
        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if ($clientContactId) {
            $query->where('client_contact_id', $clientContactId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($salesOrderNo) {
            $query->where('sales_order_no', 'LIKE', '%' . $salesOrderNo . '%');
        }
        if ($salesOrderDate) {
            $query->whereDate('sales_order_date', $salesOrderDate);
        }
        if (!empty($status)) {
            $statusArray = explode(',', $status);
            $query->whereIn('status', $statusArray);
        }
        if (!empty($productIds)) {
            $productIdArray = explode(',', $productIds);
            $query->whereHas('products', function ($query) use ($productIdArray) {
                $query->whereIn('product_id', $productIdArray);
            });
        }      
        if ($dateFrom && $dateTo) {
            $query->whereBetween('sales_order_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->whereDate('sales_order_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('sales_order_date', '<=', $dateTo);
        }

        // Get total record count before applying limit
        $totalRecords = $query->count();
        $query->offset($offset)->limit($limit);

        // Fetch paginated results
        $get_sales_orders = $query->get();

        if ($get_sales_orders->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Orders found!',
            ], 404);
        }

        // Transform Data
        $get_sales_orders->transform(function ($order) {
            $order->amount_in_words = $this->convertNumberToWords($order->total);
            $order->total = is_numeric($order->total) ? number_format((float) $order->total, 2) : $order->total;
            $order->sales_person = $order->get_user ? ['id' => $order->get_user->id, 'name' => $order->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            $order->user = $order->get_user ? ['id' => $order->get_user->id, 'name' => $order->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            unset($order->get_user);

            return $order;
        });

        // Return response for list
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Sales Orders fetched successfully!',
            'data' => $get_sales_orders,
            'count' => $get_sales_orders->count(),
            'total_records' => $totalRecords,
        ], 200);
    }

    // Update Sales Order
    public function edit_sales_order(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'name' => 'nullable|string|exists:t_clients,name',
            'sales_order_no' => 'required|string',
            'sales_order_date' => 'required|date',
            'ref_no' => 'required|string',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'sales_person' => 'required|integer|exists:users,id',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            // for products 
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
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
            'products.*.amount' => 'nullable|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',

            // for addons 
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
        ]);

        $salesOrder = SalesOrderModel::where('id', $id)->first();

        if (!$salesOrder) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Sales Order not found!'], 404);
        }

        // $exists = SalesOrderModel::where('company_id', Auth::user()->company_id)
        //     ->where('sales_order_no', $request->input('sales_order_no'))
        //     ->exists();

        // if ($exists) {
        //     return response()->json([
        //         'error' => 'The combination of company_id and sales_order_no must be unique.',
        //     ], 422);
        // }

        $salesOrderUpdated = $salesOrder->update([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name') !== null ? $request->input('name') : $salesOrder->name,
            'user' => Auth::user()->id,
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $request->input('sales_order_date'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'sales_person' => $request->input('sales_person'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesOrderProductsModel::where('sales_order_id', $id)
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
                    'channel' => $productData['channel']
                ]);
            } else {
                SalesOrderProductsModel::create([
                    'sales_order_id' => $id,
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
                    'channel' => $productData['channel']
                ]);
            }
        }

        // Process addons
        $addons = $request->input('addons');
        $requestAddonNames = [];

        foreach ($addons as $addonData) {
            $requestAddonNames[] = $addonData['name'];

            $existingAddon = SalesOrderAddonsModel::where('sales_order_id', $id)
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
                    'sales_order_id' => $id,
                    'name' => $addonData['name'],
                    'company_id' => Auth::user()->company_id,
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
                                                ->whereNotIn('product_id', $requestProductIDs)
                                                ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesOrderAddonsModel::where('sales_order_id', $id)
                                            ->whereNotIn('name', $requestAddonNames)
                                            ->delete();

        return ($salesOrderUpdated || $productsDeleted || $addonsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Order, products, and addons updated successfully!', 'data' => $salesOrder], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // Delete Sales Order
    public function delete_sales_order($id)
    {
        $company_id = Auth::user()->company_id;

        $delete_sales_order = SalesOrderModel::where('id', $id)
                                                ->where('company_id', $company_id)
                                                ->delete();

        $delete_sales_order_products = SalesOrderProductsModel::where('sales_order_id', $id)
                                                                ->where('company_id', $company_id)
                                                                ->delete();

        $delete_sales_order_addons = SalesOrderAddonsModel::where('sales_order_id', $id)
                                                            ->where('company_id', $company_id)
                                                            ->delete();

        return $delete_sales_order && $delete_sales_order_products && $delete_sales_order_addons
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Order and associated products/addons deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Sales Order not found.'], 404);
    }

    // migrate

    public function importSalesOrders()
    {
        set_time_limit(300); // Prevent timeout for large imports

        // Clear old data before import
        SalesOrderModel::truncate();
        SalesOrderProductsModel::truncate();
        SalesOrderAddonsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/sells_order.php';

        // Fetch data from external URL
        try {
            $response = Http::get($url);
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

        $successfulInserts = 0;
        $errors = [];
        $batchSize = 50;

        // **Step 1️⃣: Insert Sales Orders & Fetch IDs**
        $salesOrdersBatch = [];
        foreach ($data as $record) {
            // Retrieve client details
            $client = ClientsModel::where('name', $record['client'])->first();
            if (!$client) {
                $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
                continue;
            }

            // $clientContact = ClientContactsModel::where('customer_id', $client->customer_id)->first();
            // if (!$clientContact) {
            //     $errors[] = ['record' => $record, 'error' => 'Client contact not found for customer ID: ' . $client->customer_id];
            //     continue;
            // }

            // Map status
            $statusMapping = [1 => 'pending', 2 => 'partial', 3 => 'completed'];
            $salesOrdersBatch[] = [
                'company_id' => Auth::user()->company_id,
                'client_id' => $client->id,
                'name' => $record['client'],
                'user' => Auth::user()->id,
                'sales_order_no' => $record['so_no'],
                'sales_order_date' => date('Y-m-d', strtotime($record['so_date'] ?? now())),
                'ref_no' => $record['ref_no'] ?? null,
                'cgst' => (float)($record['tax']['cgst'] ?? 0),
                'sgst' => (float)($record['tax']['sgst'] ?? 0),
                'igst' => (float)($record['tax']['igst'] ?? 0),
                'total' => (float)($record['total'] ?? 0),
                'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                'status' => $statusMapping[$record['status']] ?? 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // **Insert Sales Orders**
        foreach (array_chunk($salesOrdersBatch, $batchSize) as $chunk) {
            SalesOrderModel::insert($chunk);
        }

        // **Fetch Inserted Sales Order IDs**
        $salesOrderIds = SalesOrderModel::whereIn('sales_order_no', array_column($salesOrdersBatch, 'sales_order_no'))
            ->pluck('id', 'sales_order_no')
            ->toArray();

        // **Step 2️⃣: Insert Products**
        $productsBatch = [];
        foreach ($data as $record) {
            $salesOrderId = $salesOrderIds[$record['so_no']] ?? null;
            if (!$salesOrderId) {
                continue; // Skip if sales order is not found
            }

            $itemsData = json_decode($record['items'] ?? '{}', true);

            if (!empty($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {
                    $get_product = ProductsModel::where('name', $product)->first();
                    if (!$get_product) {
                        $errors[] = ['record' => $itemsData, 'error' => "Product not found: '{$product}'"];
                        continue;
                    }

                    $productsBatch[] = [
                        'sales_order_id' => $salesOrderId,
                        'company_id' => Auth::user()->company_id,
                        'product_id' => $get_product->id,
                        'product_name' => $product,
                        'description' => $itemsData['desc'][$index] ?? '',
                        // 'group' => $itemsData['brand'][$index] ?? '',
                        'quantity' => is_numeric($itemsData['quantity'][$index]) ? (int)$itemsData['quantity'][$index] : 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => is_numeric($itemsData['price'][$index]) ? (float)$itemsData['price'][$index] : 0,
                        
                        
                        'discount_type' => 'percentage',
                        'discount' => is_numeric($itemsData['discount'][$index]) ? (float)$itemsData['discount'][$index] : 0,
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => is_numeric($itemsData['tax'][$index]) ? (float)$itemsData['tax'][$index] : 0,
                        'cgst' => (float)($itemsData['cgst'][$index] ?? 0),
                        'sgst' => (float)($itemsData['sgst'][$index] ?? 0),
                        'igst' => (float)($itemsData['igst'][$index] ?? 0),
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
                        'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index]) 
                                ? (
                                    is_numeric($itemsData['channel'][$index]) 
                                        ? (float)$itemsData['channel'][$index] 
                                        : (
                                            strtolower($itemsData['channel'][$index]) === 'standard' ? 1 :
                                            (strtolower($itemsData['channel'][$index]) === 'non-standard' ? 2 :
                                            (strtolower($itemsData['channel'][$index]) === 'cbs' ? 3 : null))
                                        )
                                ) 
                                : null,
                        'sent' => is_numeric($itemsData['sent'][$index]) ? (int)$itemsData['sent'][$index] : 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        // **Insert Products in Batches**
        foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
            SalesOrderProductsModel::insert($chunk);
        }

        // **Step 3️⃣: Insert Addons**
        $addonsBatch = [];
        foreach ($data as $record) {
            $salesOrderId = $salesOrderIds[$record['so_no']] ?? null;
            if (!$salesOrderId) {
                continue;
            }

            $addonsData = json_decode($record['addons'] ?? '{}', true);

            if (!empty($addonsData)) {
                foreach ($addonsData as $name => $values) {
                    $addonsBatch[] = [
                        'sales_order_id' => $salesOrderId,
                        'company_id' => Auth::user()->company_id,
                        'name' => $name,
                        'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
                        'tax' => 18,
                        'hsn' => $values['hsn'] ?? '',
                        'cgst' => (float)($values['cgst'] ?? 0),
                        'sgst' => (float)($values['sgst'] ?? 0),
                        'igst' => (float)($values['igst'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
            SalesOrderAddonsModel::insert($chunk);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Sales orders import completed successfully.",
            'errors' => $errors,
        ], 200);
    }

    // export
    public function export_sales_orders(Request $request)
    {
        // Check for comma-separated IDs
        $ids = $request->input('id') ? explode(',', $request->input('id')) : null;

        // Get filter inputs
        $clientId = $request->input('client_id');
        $name = $request->input('name');
        $salesOrderNo = $request->input('sales_order_no');
        $salesOrderDate = $request->input('sales_order_date');

        // Build the query
        $query = SalesOrderModel::query()
            ->select(
                'id', 
                'client_id', 
                'name', 
                'sales_order_no', 
                'sales_order_date', 
                'ref_no', 
                'cgst', 
                'sgst', 
                'igst', 
                'total', 
                'template', 
                'status'
            )
            ->where('company_id', Auth::user()->company_id);

        // If IDs are provided, prioritize them
        if ($ids) {
            $query->whereIn('id', $ids);
        } else {
            // Apply filters only if IDs are not provided
            if ($clientId) {
                $query->where('client_id', $clientId);
            }
            if ($name) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            }
            if ($salesOrderNo) {
                $query->where('sales_order_no', 'LIKE', '%' . $salesOrderNo . '%');
            }
            if ($salesOrderDate) {
                $query->whereDate('sales_order_date', $salesOrderDate);
            }
        }

        $salesOrders = $query->get();

        if ($salesOrders->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Orders found to export!',
            ], 404);
        }

        // Format data for Excel
        $exportData = $salesOrders->map(function ($order) {
            return [
                'Sales Order ID' => $order->id,
                'Client ID' => $order->client_id,
                'Name' => $order->name,
                'Sales Order No' => $order->sales_order_no,
                'Sales Order Date' => $order->sales_order_date,
                'Ref No' => $order->ref_no,
                'CGST' => $order->cgst,
                'SGST' => $order->sgst,
                'IGST' => $order->igst,
                'Total' => $order->total,
                'Template' => $order->template,
                'Status' => $order->status,
            ];
        })->toArray();

        // Generate the file path
        $fileName = 'sales_orders_export_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'uploads/sales_orders_excel/' . $fileName;

        // Save Excel to storage
        Excel::store(new class($exportData) implements FromCollection, WithHeadings {
            private $data;

            public function __construct(array $data)
            {
                $this->data = collect($data);
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Sales Order ID',
                    'Client ID',
                    'Name',
                    'Sales Order No',
                    'Sales Order Date',
                    'Reference No',
                    'CGST',
                    'SGST',
                    'IGST',
                    'Total',
                    'Template',
                    'Status',
                ];
            }
        }, $filePath, 'public');

        // Get file details
        $fileUrl = asset('storage/' . $filePath);
        $fileSize = Storage::disk('public')->size($filePath);

        // Return response with file details
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'File available for download',
            'data' => [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'content_type' => 'Excel',
            ],
        ], 200);
    }

    public function getPendingSupplierseOrders(Request $request)
    {
        // Validate request
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
        ]);

        // Get authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['code' => 401, 'success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Fetch pending purchase orders for the given supplier and authenticated company
        $saleseOrders = SalesOrderModel::where('client_id', $request->input('client_id'))
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->pluck('ref_no'); // Fetch only `ref_no`

        // Check if any records exist
        if ($saleseOrders->isEmpty()) {
            return response()->json(['code' => 201, 'success' => true, 'message' => 'No pending sales orders found.'], 404);
        }

        // Return the result
        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Pending Suppliers record fetched successfully!',
            'data' => $saleseOrders
        ], 200);
    }

    public function getPendingPartialSalesOrders()
    {
        // Validate request
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
        ]);

        // Get authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch pending sales orders for the given supplier and authenticated company
        $get_SalesOrders = SalesOrderModel::where('client_id', $request->input('client_id'))
                                            ->where('company_id', $user->company_id)
                                            ->whereIn('status', ['pending', 'partial'])
                                            ->select('id', 'sales_order_no') // Fetch both `id` and `sales_order_no`
                                            ->get();

        // Check if any records exist
        if ($get_SalesOrders->isEmpty()) {
            return response()->json(['message' => 'No pending sales orders found.'], 404);
        }

        // Return the result
        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Pending and partial orders fetched successfully!',
            'data' => $get_SalesOrders
        ], 200);
    }

}
