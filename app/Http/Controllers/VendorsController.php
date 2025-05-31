<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\VendorsModel;
use Illuminate\Http\Request;

class VendorsController extends Controller
{
    //create
    public function create(Request $request)
    {
        try {
            // Validate incoming request
            $validated = $request->validate([
                'name'   => 'required|string|max:255',
                'gstin'  => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:255',
                'email'  => 'nullable|email|max:255',
            ]);

            // Generate unique vendor_id (8 digits)
            do {
                $vendor_id = rand(11111111, 99999999);
                $exists = VendorsModel::where('vendor_id', $vendor_id)->exists();
            } while ($exists);

            // Get company_id from authenticated user
            $company_id = Auth::user()->company_id;

            $name = $request->input('name');
            $gstin = $request->input('gstin');

            // Check uniqueness
            $exists = VendorsModel::where('company_id', $company_id)
                ->where('name', $name)
                ->where('gstin', $gstin)
                ->exists();

            if ($exists) {
                return response()->json([
                    'code' => 409,
                    'success' => false,
                    'message' => 'A vendor with this name and GSTIN already exists in your company.',
                ], 409);
            }

            // Create new Vendor
            $vendor = VendorsModel::create([
                'vendor_id'  => $vendor_id,
                'company_id' => $company_id,
                'name'       => $name,
                'gstin'      => $gstin ?? null,
                'mobile'     => $validated['mobile'] ?? null,
                'email'      => $validated['email'] ?? null,
            ]);

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Vendor created successfully!',
                'data'    => $vendor->makeHidden(['created_at', 'updated_at'])
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $ex) {
            // Validation errors
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $ex->errors()
            ], 422);

        } catch (\Exception $ex) {
            // Other errors
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $ex->getMessage()
            ], 500);
        }
    }

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $name = $request->input('name'); // Filter by name (optional)

            $company_id = Auth::user()->company_id;

            $query = VendorsModel::where('company_id', $company_id);

            // Fetch single vendor by id if provided
            if ($id) {
                $vendor = $query->where('id', $id)->first();

                if (!$vendor) {
                    return response()->json([
                        'code'    => 404,
                        'success' => false,
                        'message' => 'Vendor not found.'
                    ], 404);
                }

                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'Vendor fetched successfully!',
                    'data'    => $vendor
                ], 200);
            }

            // Filter by vendor name (case-insensitive, partial match)
            if ($name) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            }

            $total = $query->count();

            // Paginate
            $vendors = $query->orderBy('name', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($vendors->isEmpty()) {
                return response()->json([
                    'code'    => 200,
                    'success' => false,
                    'message' => 'No vendors found!',
                    'data'    => [],
                    'count'   => 0,
                    'total_records' => $total
                ], 200);
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Vendors fetched successfully!',
                'data'    => $vendors,
                'count'   => $vendors->count(),
                'total_records' => $total
            ], 200);

        } catch (\Exception $ex) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $ex->getMessage()
            ], 500);
        }
    }

    // update
    public function update(Request $request, $id)
    {
        try {
            $company_id = Auth::user()->company_id;

            // Find the vendor
            $vendor = VendorsModel::where('company_id', $company_id)
                ->where('id', $id)
                ->first();

            if (!$vendor) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Vendor not found.'
                ], 404);
            }

            // Validate request data
            $validated = $request->validate([
                'name'   => 'required|string|max:255',
                'gstin'  => 'nullable|string|max:32',
                'mobile' => 'nullable|string|max:15',
                'email'  => 'nullable|email|max:255',
            ]);

            $name = $request->input('name');
            $gstin = $request->input('gstin');

            // Check uniqueness excluding current vendor
            $exists = VendorsModel::where('company_id', $company_id)
                ->where('name', $name)
                ->where('gstin', $gstin)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'code' => 409,
                    'success' => false,
                    'message' => 'Another vendor with this name and GSTIN already exists in your company.',
                ], 409);
            }

            // Column-based update
            $vendor->name   = $validated['name'];
            $vendor->gstin  = $validated['gstin'] ?? null;
            $vendor->mobile = $validated['mobile'] ?? null;
            $vendor->email  = $validated['email'] ?? null;
            $vendor->save();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Vendor updated successfully!',
                'data'    => $vendor
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Catch validation errors
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Other errors
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $company_id = Auth::user()->company_id;

            // Find the vendor by ID and company
            $vendor = VendorsModel::where('company_id', $company_id)
                ->where('id', $id)
                ->first();

            if (!$vendor) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Vendor not found.'
                ], 404);
            }

            // Optionally: you can check if vendor is associated with any transactions before deleting

            // Delete the vendor
            $vendor->delete();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Vendor deleted successfully!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }
}
