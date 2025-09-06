<?php

namespace App\Http\Controllers;
use App\Models\StateModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StateController extends Controller
{
    //
    // Register multiple states
    public function registerStates(Request $request)
    {
        // Validate the input to ensure it's an array
        $request->validate([
            'states' => 'required|array',
            'states.*' => 'string|max:255', // Validate each state name
            'country_id' => [
                'required',
                'numeric',
                Rule::exists('t_countries', 'id'), // Ensure country_id exists in t_country table
            ],
        ]);

        $states = array_unique(array_map('trim', $request->input('states'))); // Remove duplicates and trim whitespace

        $inserted = [];
        $skipped = [];

        foreach ($states as $state) {
            $state = ucfirst(strtolower($state)); // Normalize state names (optional)

            // Check if the state already exists
            $existing = StateModel::where('name', $state)->exists();

            if (!$existing) {
                StateModel::create(['name' => $state, 'country_id' => $request->input('country_id')]);
                $inserted[] = $state;
            } else {
                $skipped[] = $state;
            }
        }

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'State registration completed!',
            'inserted' => $inserted,
            'skipped' => $skipped,
        ]);
    }

    // View all states
    public function viewStates()
    {
        $states = StateModel::with('country:name,id')->get()->makeHidden(['created_at', 'updated_at', 'country_id']);
    
        $formattedStates = $states->map(function ($state) {
            return [
                'id' => $state->id,
                'name' => $state->name,
                'country' => $state->country->name ?? null, // Include country name
            ];
        });
    
        return response()->json(['code' => 200, 'success' => true, 'data' => $formattedStates, 'count' => count($formattedStates)]);
    }
    

    // Update a state
    public function updateState(Request $request, $id)
    {
        $state = StateModel::find($id);
        if (!$state) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'State not found!'], 404);
        }

        // Validate the input
        $request->validate([
            'name' => 'required|string|max:255|unique:t_states,name,' . $id,
            'country_id' => [
                'required',
                'numeric',
                Rule::exists('t_countries', 'id'), // Ensure country_id exists in t_country table
            ],
        ]);

        $state->update(['name' => $request->input('name')]);
        return response()->json(['code' => 200, 'success' => true, 'message' => 'State updated successfully!']);
    }

    // Delete a state
    public function deleteState($id)
    {
        $state = StateModel::find($id);
        if (!$state) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'State not found!'], 404);
        }

        $state->delete();
        return response()->json(['code' => 204, 'success' => true, 'message' => 'State deleted successfully!']);
    }

    // View all states by country name
    public function viewStatesByCountry($country_name)
    {
        try {
            // Retrieve all states belonging to the country
            $states = StateModel::where('country_name', $country_name)
                                ->get()
                                ->makeHidden(['created_at', 'updated_at', 'country_id']);
            
            // Format the states for response
            $formattedStates = $states->map(function ($state) {
                return [
                    'id' => $state->id,
                    'name' => $state->name,
                    'country' => $state->country_name ?? null, // Include country name
                ];
            });

            // Return the formatted states
            return response()->json([
                'code' => 200,
                'success' => true,
                'data' => $formattedStates,
                'count' => count($formattedStates),
            ]);

        } catch (\Exception $e) {
            // Return error response in case of any exception
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while fetching states',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
