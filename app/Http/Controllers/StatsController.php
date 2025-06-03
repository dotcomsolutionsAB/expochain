<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
        $counts = [
            'Adjustments' => AdjustmentModel::count(),
            'Assembly' => AssemblyModel::count(),
            'Assembly Operation' => AssemblyOperationModel::count(),
            'Assembly Operation Products' => AssemblyOperationProductsModel::count(),
            'Assembly Products' => AssemblyProductsModel::count(),
            'Clients' => ClientsModel::count(),
        ];

        // Build HTML string for table
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Stats Overview</title>
<style>
    body {font-family: Arial, sans-serif; margin: 40px auto; max-width: 600px; background: #f9f9f9;}
    table {width: 100%; border-collapse: collapse; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1);}
    th, td {padding: 12px 20px; border: 1px solid #ddd; text-align: left;}
    th {background-color: #007bff; color: white;}
    caption {font-size: 1.5em; margin-bottom: 10px; font-weight: bold; color: #333;}
    tbody tr:hover {background-color: #f1f7ff;}
</style>
</head>
<body>
<table>
    <caption>Database Table Counts</caption>
    <thead>
        <tr>
            <th>Model Name</th>
            <th>Total Records</th>
        </tr>
    </thead>
    <tbody>';

        foreach ($counts as $model => $count) {
            $html .= '<tr><td>' . htmlspecialchars($model) . '</td><td>' . htmlspecialchars($count) . '</td></tr>';
        }

        $html .= '</tbody></table></body></html>';

        // Return HTML response with correct header
        return response($html, 200)
              ->header('Content-Type', 'text/html');
    }
}
