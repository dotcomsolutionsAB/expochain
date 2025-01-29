<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CounterModel;
use Auth;

class CounterController extends Controller
{
    //
     /**
     * Create a new counter.
     */
    public function add_counter(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:t_counters,name',
            'type' => 'required|in:manual,auto',
            'prefix' => 'nullable|string',
            'next_number' => 'required|integer|min:1',
            'postfix' => 'nullable|string',
        ]);

        $counter = CounterModel::create([
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'prefix' => $request->input('prefix'),
            'next_number' => $request->input('next_number'),
            'postfix' => $request->input('postfix'),
        ]);

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Counter created successfully!',
            'data' => $counter->makeHidden('id', 'created_at', 'updated_at'),
        ], 201);
    }

    /**
     * Read a specific counter or all counters.
     */
    public function view_counter(Request $request, $id = null)
    {
        if ($id) {
            $counter = CounterModel::find($id);
            if ($counter) {
                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Counter fetched successfully!',
                    'data' => $counter->makeHidden(['created_at', 'updated_at']),
                ]);
            }
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Counter not found!'], 404);
        }

        // Fetch counters with optional name filter
        $name = $request->input('name'); // Get the name from the request if passed
        $company_id = $request->input('company_id'); // Get the company_id from the request if passed

        $counters = CounterModel::when($name, function ($query, $name) {
            $query->where('name', 'LIKE', '%' . $name . '%'); // Apply name filter
        })
        ->when($company_id, function ($query, $company_id) {
            $query->where('company_id', $company_id); // Apply company_id filter
        })
        ->get();

        return $counters->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Counters fetched successfully!',
                'data' => $counters->makeHidden(['id', 'created_at', 'updated_at']),
            ])
            : response()->json(['code' => 200, 'success' => false, 'message' => 'No counters found!', 'data' => []], 200);
    }



    /**
     * Update a counter.
     */
    public function edit_counter(Request $request, $id)
    {
        $request->validate([
            // 'name' => 'nullable|string|unique:t_counters,name,' . $id,
            'type' => 'nullable|in:manual,auto',
            'prefix' => 'nullable|string',
            'next_number' => 'nullable|integer|min:1',
            'postfix' => 'nullable|string',
        ]);

        $counter = CounterModel::find($id);

        if (!$counter) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Counter not found!'], 404);
        }

        // $counter->update($request->only(['name', 'type', 'prefix', 'next_number', 'postfix']));
        $counter->update($request->only(['type', 'prefix', 'next_number', 'postfix']));

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Counter updated successfully!',
            'data' => $counter,
        ]);
    }

    /**
     * Delete a counter.
     */
    public function delete_counter($id)
    {
        $counter = CounterModel::find($id);

        if (!$counter) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Counter not found!'], 404);
        }

        $counter->delete();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Counter deleted successfully!',
        ]);
    }

}
