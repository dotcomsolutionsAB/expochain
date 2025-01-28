<?php

namespace App\Http\Controllers;
use App\Models\CountryModel;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    //
    // Register multiple countries
    public function registerCountries(Request $request)
    {
       // Validate the input to ensure it's an array
        $request->validate([
            'countries' => 'required|array',
            'countries.*' => 'string|max:255', // Validate each country name
        ]);

        $countries = array_unique(array_map('trim', $request->input('countries'))); // Remove duplicates and trim whitespace

        // Move "India" to the top of the list
        $countries = array_values(array_filter($countries, fn($country) => strcasecmp($country, 'India') !== 0));
        array_unshift($countries, 'India'); // Add "India" at the beginning

        $inserted = [];
        $skipped = [];

        foreach ($countries as $country) {
            $country = ucfirst(strtolower($country)); // Normalize country names (optional)

            // Check if the country already exists
            $existing = CountryModel::where('name', $country)->exists();

            if (!$existing) {
                CountryModel::create(['name' => $country]);
                $inserted[] = $country;
            } else {
                $skipped[] = $country;
            }
        }

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Country registration completed!',
            'inserted' => $inserted,
            'skipped' => $skipped,
        ]);
    }

    // View all countries
    public function viewCountries()
    {
        $countries = CountryModel::all()->makeHidden(['created_at', 'updated_at']);
        return response()->json(['code' => 200, 'success' => true, 'data' => $countries, 'count' => count($countries)]);
    }

    // Update a country
    public function updateCountry(Request $request, $id)
    {
        $country = CountryModel::find($id);
        if (!$country) {
            return response()->json(['code' => 404, 'success' => false,'message' => 'Country not found!'], 404);
        }

        // Validate the input
        $request->validate([
            'name' => 'required|string|max:255|unique:t_countries,name,' . $id,
        ]);

        $country->update(['name' => $request->input('name')]);
        return response()->json(['code' => 200, 'success' => true, 'message' => 'Country updated successfully!']);
    }

    // Delete a country
    public function deleteCountry($id)
    {
        $country = CountryModel::find($id);
        if (!$country) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Country not found!'], 404);
        }

        $country->delete();
        return response()->json(['code' => 204,'success' => true, 'message' => 'Country deleted successfully!']);
    }

}
