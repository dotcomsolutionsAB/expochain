<?php

namespace App\Http\Controllers;
use App\Models\UploadsModel;
use App\Models\CustomerVisitModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class CustomerVisitController extends Controller
{
    //
    // add
    public function register_customer_visit(Request $request)
    {
        try {
            // Step 1: Validate input
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'customer' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'contact_person_name' => 'nullable|string|max:255',
                'designation' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'champion' => 'nullable|numeric',
                'fenner' => 'nullable|numeric',
                'details' => 'nullable|string',
                'growth' => 'nullable|string',
                'expense' => 'nullable|string|max:255',
                'amount_expense' => 'nullable|numeric',
                'uploads.*' => 'nullable|file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 200);
            }

            $validated = $validator->validated();

            // Step 2: Handle uploads
            $uploadedIds = [];

            if ($request->hasFile('uploads')) {
                foreach ($request->file('uploads') as $file) {
                    $ext = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(20) . '.' . $ext;

                    $relativePath = 'uploads/customer_visit/' . $filename;
                    // $file->storeAs('public/' . dirname($relativePath), basename($relativePath));
                    Storage::disk('public')->putFileAs('uploads/customer_visit', $file, $filename);

                    $upload = UploadsModel::create([
                        'company_id' => Auth::user()->company_id,
                        'file_ext' => $ext,
                        'file_url' => $relativePath, // relative path only
                        'file_size' => $file->getSize(),
                        'file_name' => $originalName,
                    ]);

                    $uploadedIds[] = $upload->id;

                    unset($upload['id'], $upload['created_at'], $upload['updated_at']);
                }
            }

            // Step 3: Store customer visit record
            $register_visit = CustomerVisitModel::create([
                'company_id' => Auth::user()->company_id,
                'date' => $validated['date'],
                'customer' => $validated['customer'],
                'location' => $validated['location'] ?? null,
                'contact_person_name' => $validated['contact_person_name'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'champion' => $validated['champion'] ?? 0,
                'fenner' => $validated['fenner'] ?? 0,
                'details' => $validated['details'] ?? null,
                'growth' => $validated['growth'] ?? null,
                'expense' => $validated['expense'] ?? null,
                'amount_expense' => $validated['amount_expense'] ?? 0,
                'upload' => !empty($uploadedIds)
                    ? implode(',', array_filter($uploadedIds))
                    : null,
            ]);

            unset($register_visit['id'], $register_visit['company_id'], $register_visit['created_at'], $register_visit['updated_at']);

            return response()->json([
                'code' => 201,
                'success' => true,
                'message' => 'Customer visit created successfully.',
                'data' => $register_visit,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while creating the customer visit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // fetch
    public function fetch(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $search = $request->input('search', '');

             $query = CustomerVisitModel::where('company_id', Auth::user()->company_id); // Filter by company_id

            // Apply search filters
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer', 'like', "%$search%")
                    ->orWhere('location', 'like', "%$search%")
                    ->orWhere('contact_person_name', 'like', "%$search%")
                    ->orWhere('expense', 'like', "%$search%");
                });
            }

            $totalRecords = $query->count();

            $visits = $query
                ->orderByDesc('date')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Convert upload IDs to actual file URLs
            foreach ($visits as $visit) {
                $uploadIds = array_filter(explode(',', $visit->upload));
                $uploadUrls = [];

                if (!empty($uploadIds)) {
                    $uploads = UploadsModel::whereIn('id', $uploadIds)->get();
                    foreach ($uploads as $upload) {
                        $uploadUrls[] = asset('storage/' . $upload->file_url);
                    }
                }

                $visit->upload_urls = $uploadUrls;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Customer visits fetched successfully.',
                'count' => count($visits),
                'total_records' => $totalRecords,
                'data' => $visits,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch customer visits.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // update
    public function edit(Request $request, $id)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'customer' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'contact_person_name' => 'nullable|string|max:255',
                'designation' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'champion' => 'nullable|numeric',
                'fenner' => 'nullable|numeric',
                'details' => 'nullable|string',
                'growth' => 'nullable|string',
                'expense' => 'nullable|string|max:255',
                'amount_expense' => 'nullable|numeric',
                'uploads.*' => 'nullable|file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 200);
            }

            $validated = $validator->validated();

            // Find the existing record
            $visit = CustomerVisitModel::find($id);
            $existingUploadIds = array_filter(explode(',', $visit->upload));

            // Process new uploads if provided
            $newUploadIds = [];

            if ($request->hasFile('uploads')) {
                foreach ($request->file('uploads') as $file) {
                    $ext = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(20) . '.' . $ext;
                    $relativePath = 'uploads/customer_visit/' . $filename;

                    // $file->storeAs('public/' . dirname($relativePath), basename($relativePath));
                    Storage::disk('public')->putFileAs('uploads/customer_visit', $file, $filename);

                    $upload = UploadsModel::create([
                        'company_id' => Auth::user()->company_id,
                        'file_ext' => $ext,
                        'file_url' => $relativePath, // relative only
                        'file_size' => $file->getSize(),
                        'file_name' => $originalName,
                    ]);

                    $newUploadIds[] = $upload->id;

                    unset($upload['id'], $upload['created_at'], $upload['updated_at']);
                }
            }

            $allUploadIds = array_merge($existingUploadIds, $newUploadIds);

            // Update fields
            $visit->company_id = Auth::user()->company_id;
            $visit->date = $validated['date'];
            $visit->customer = $validated['customer'];
            $visit->location = $validated['location'] ?? null;
            $visit->contact_person_name = $validated['contact_person_name'] ?? null;
            $visit->designation = $validated['designation'] ?? null;
            $visit->mobile = $validated['mobile'] ?? null;
            $visit->email = $validated['email'] ?? null;
            $visit->champion = $validated['champion'] ?? 0;
            $visit->fenner = $validated['fenner'] ?? 0;
            $visit->details = $validated['details'] ?? null;
            $visit->growth = $validated['growth'] ?? null;
            $visit->expense = $validated['expense'] ?? null;
            $visit->amount_expense = $validated['amount_expense'] ?? 0;
            $visit->upload = count($allUploadIds) > 0 ? implode(',', $allUploadIds) : null;
            $visit->save();

            // Generate resolved file URLs
            $uploadUrls = [];
            if (!empty($allUploadIds)) {
                $uploads = UploadsModel::whereIn('id', $allUploadIds)->get();
                foreach ($uploads as $u) {
                    $uploadUrls[] = asset('storage/' . $u->file_url);
                }
            }

            unset($visit['id'], $visit['upload'], $visit['created_at'], $visit['updated_at']);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Customer visit updated successfully.',
                'data' => $visit,
                'upload_urls' => $uploadUrls,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while updating the customer visit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // delete specific uploads
    public function deleteUploads(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'delete_ids' => 'required|string' // e.g. "12,14"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 200);
            }

            $visit = CustomerVisitModel::find($id);
            $deleteIds = array_filter(explode(',', $request->delete_ids));
            $existingIds = array_filter(explode(',', $visit->upload));

            // Filter IDs that actually exist in current upload
            $toDelete = array_intersect($existingIds, $deleteIds);

            if (empty($toDelete)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid upload IDs found to delete.',
                ], 200);
            }

            // Delete files from server and DB
            $uploads = UploadsModel::whereIn('id', $toDelete)->get();

            foreach ($uploads as $upload) {
                $filePath = storage_path('app/public/' . $upload->file_url);
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
                $upload->delete();
            }

            // Update visit upload column
            $remainingIds = array_diff($existingIds, $toDelete);
            $visit->upload = count($remainingIds) > 0 ? implode(',', $remainingIds) : null;
            $visit->save();

            // Get updated upload URLs
            $updatedUrls = [];
            if (!empty($remainingIds)) {
                $remainingUploads = UploadsModel::whereIn('id', $remainingIds)->get();
                foreach ($remainingUploads as $file) {
                    $updatedUrls[] = asset('storage/' . $file->file_url);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Selected files deleted successfully.',
                'upload_ids' => $remainingIds,
                'upload_urls' => $updatedUrls
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting files.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // delete
    public function delete($id)
    {
        try {
            $visit = CustomerVisitModel::find($id);
            $uploadIds = array_filter(explode(',', $visit->upload));

            // Delete upload files and records
            if (!empty($uploadIds)) {
                $uploads = UploadsModel::whereIn('id', $uploadIds)->get();
                foreach ($uploads as $upload) {
                    $filePath = storage_path('app/public/' . $upload->file_url);
                    if (File::exists($filePath)) {
                        File::delete($filePath);
                    }
                    $upload->delete();
                }
            }

            // Delete customer visit record
            $visit->delete();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Customer visit and associated files deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while deleting the visit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // import
    public function importCustomerVisits()
    {
        $url = 'https://expo.egsm.in/assets/custom/migrate/customer_visit.php';

        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch data from external source.',
                'error' => $e->getMessage()
            ], 500);
        }

        if ($response->failed()) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'External data fetch failed.'
            ], 500);
        }

        $data = $response->json();

        if (empty($data['data'])) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No data found in response.'
            ], 404);
        }

        $importData = [];
        $companyId = Auth::user()->company_id;

        foreach ($data['data'] as $record) {
            // Decode JSON fields
            $contact = json_decode($record['contact_person'] ?? '{}', true);
            $annual = json_decode($record['annual_purchase'] ?? '{}', true);

            $importData[] = [
                'company_id' => $companyId,
                'date' => $record['date'],
                'customer' => $record['customer'],
                'location' => $record['location'],
                'contact_person_name' => $contact['name'] ?? null,
                'designation' => $contact['designation'] ?? null,
                'mobile' => $contact['mobile'] ?? null,
                'email' => $contact['email'] ?? null,
                'champion' => $annual['champion'] ?? null,
                'fenner' => $annual['fenner'] ?? null,
                'details' => $record['details'],
                'growth' => $record['growth'],
                'expense' => $record['expense'],
                'amount_expense' => $record['expense_amount'] ?: 0,
                'upload' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // 🔁 Batch-wise insert (in chunks to prevent memory issues)
        $chunkSize = 100;
        foreach (array_chunk($importData, $chunkSize) as $chunk) {
            CustomerVisitModel::insert($chunk);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Customer visits imported successfully.',
            'total_imported' => count($importData),
        ]);
    }
}
