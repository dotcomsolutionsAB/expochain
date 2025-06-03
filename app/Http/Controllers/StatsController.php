<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// Import your models
use App\Models\AdjustmentModel;
use App\Models\AssemblyModel;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;
use App\Models\AssemblyProductsModel;
use App\Models\ClientsModel;

class StatsController extends Controller
{
    public function index()
    {
        $response = [
            'total_adjustments' => AdjustmentModel::count(),
            'total_assembly' => AssemblyModel::count(),
            'total_assembly_operation' => AssemblyOperationModel::count(),
            'total_assembly_operation_products' => AssemblyOperationProductsModel::count(),
            'total_assembly_products' => AssemblyProductsModel::count(),
            'total_clients' => ClientsModel::count(),
        ];

        return response()->json($response);
    }
}
