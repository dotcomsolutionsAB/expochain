<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// Import your models here
use App\Models\AdjustmentModel;
use App\Models\AssemblyModel;
use App\Models\AssemblyOperationModel;
use App\Models\ClientsModel;

class StatsController extends Controller
{
    public function index()
    {
        // Fetch counts using Eloquent count()
        $adjustmentsCount = AdjustmentModel::count();
        $assemblyCount = AssemblyModel::count();
        $assemblyOperationCount = AssemblyOperationModel::count();
        $clientsCount = ClientsModel::count();

        // Prepare response array
        $response = [
            'total_adjustments' => $adjustmentsCount,
            'total_assembly' => $assemblyCount,
            'total_assembly_operation' => $assemblyOperationCount,
            'total_clients' => $clientsCount,
        ];

        // Return JSON response
        return response()->json($response);
    }
}
