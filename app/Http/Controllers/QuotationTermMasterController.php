<?php

namespace App\Http\Controllers;
use App\Models\QuotationTermMasterModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Auth;

class QuotationTermMasterController extends Controller
{
    //
    // Add API
    public function add(Request $request)
    {
        // Get company_id from authenticated user
        $company_id = Auth::user()->company_id;

        // Custom validation
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('t_quotation_term_masters')->where(function ($query) use ($company_id) {
                    return $query->where('company_id', $company_id);
                })
            ],
            'default_value' => 'nullable|string|max:255',
            'type' => 'required|in:textbox,dropdown',
        ]);

        // If validation fails, return a structured error response
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validation failed!',
                'data' => $validator->errors() // Returning errors inside "data"
            ], 422);
        }
        $order = QuotationTermMasterModel::where('company_id', Auth::user()->company_id)->max('order') + 1;

        $term = QuotationTermMasterModel::create([
            'company_id' => Auth::user()->company_id,
            'order' => $order,
            'name' => $request->input('name'),
            'default_value' => $request->input('default_value'),
            'type' => $request->input('type'),
        ]);

        return response()->json(['code' => 201, 'success' => true, 'message' => 'Term added successfully!', 'data' => $term->makeHidden(['id', 'created_at', 'updated_at'])]);
    }

    // Retrieve API
    public function retrieve(Request $request)
    {
        $terms = QuotationTermMasterModel::where('company_id', Auth::user()->company_id)
            ->orderBy('order')
            ->get(['id', 'name', 'default_value as default', 'type']);

        return response()->json(['code' => 200, 'success' => true, 'data' => $terms, 'count' => count($terms)]);
    }

    // Update API
    public function update(Request $request, $id)
    {
        $term = QuotationTermMasterModel::find($id);

        if (!$term) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Term not found!']);
        }

        // Get company_id from the existing term
        $company_id = $term->company_id;

        // Validate request input
        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable', 
                'string', 
                'max:255', 
                Rule::unique('t_quotation_term_masters')->where(function ($query) use ($company_id, $id) {
                    return $query->where('company_id', $company_id)->where('id', '!=', $id);
                })
            ],
            'default_value' => 'nullable|string|max:255',
        ]);

        // If validation fails, return structured error response
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validation failed!',
                'data' => $validator->errors()
            ], 422);
        }


        // Update only if values are provided, else retain old values
        $term->update([
            'name' => $request->filled('name') ? $request->input('name') : $term->name,
            'default_value' => $request->filled('default_value') ? $request->input('default_value') : $term->default_value,
        ]);

        return response()->json(['code' => 200, 'success' => true, 'message' => 'Term updated successfully!', 'data' => $term->makeHidden(['id', 'created_at', 'updated_at'])]);
    }

    public function delete($id)
    {
        $term = QuotationTermMasterModel::find($id);

        if (!$term) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Term not found!'
            ], 404);
        }

        $term->delete();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Term deleted successfully!'
        ], 200);
    }

}
