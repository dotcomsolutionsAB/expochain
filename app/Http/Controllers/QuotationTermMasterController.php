<?php

namespace App\Http\Controllers;
use App\Models\QuotationTermMasterModel;
use Illuminate\Http\Request;
use Auth;

class QuotationTermMasterController extends Controller
{
    //
    // Add API
    public function add(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:t_quotation_term_masters,name,NULL,id,company_id,' . $request->input('company_id'),
            'default_value' => 'nullable|string|max:255',
            'type' => 'required|in:textbox,dropdown',
        ]);

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

        $request->validate([
            'default_value' => 'nullable|string|max:255',
        ]);

        $term->update(['default_value' => $request->input('default_value')]);

        return response()->json(['code' => 200, 'success' => true, 'message' => 'Term updated successfully!', 'data' => $term->makeHidden(['id', 'created_at', 'updated_at'])]);
    }
}
