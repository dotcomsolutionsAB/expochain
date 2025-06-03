<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// Initial 6 models
use App\Models\AdjustmentModel;
use App\Models\AssemblyModel;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;
use App\Models\AssemblyProductsModel;
use App\Models\ClientsModel;

// Additional models you provided
use App\Models\CategoryModel;
use App\Models\ChannelModel;
use App\Models\ClientAddressModel;
use App\Models\ClientContactsModel;
use App\Models\ClosingStockModel;
use App\Models\CompanyModel;
use App\Models\CounterModel;
use App\Models\CountryModel;
use App\Models\CreditNoteModel;
use App\Models\CreditNoteProductsModel;
use App\Models\CustomerVisitModel;
use App\Models\DebitNoteModel;
use App\Models\DebitNoteProductsModel;

class StatsController extends Controller
{
    public function index()
    {
        // Collect counts for all models here
        $counts = [
            // Initial 6 models
            'Adjustments' => AdjustmentModel::count(),
            'Assembly' => AssemblyModel::count(),
            'Assembly Operation' => AssemblyOperationModel::count(),
            'Assembly Operation Products' => AssemblyOperationProductsModel::count(),
            'Assembly Products' => AssemblyProductsModel::count(),
            'Clients' => ClientsModel::count(),

            // Additional models
            'Category' => CategoryModel::count(),
            'Channel' => ChannelModel::count(),
            'Client Address' => ClientAddressModel::count(),
            'Client Contacts' => ClientContactsModel::count(),
            'Closing Stock' => ClosingStockModel::count(),
            'Company' => CompanyModel::count(),
            'Counter' => CounterModel::count(),
            'Country' => CountryModel::count(),
            'Credit Note' => CreditNoteModel::count(),
            'Credit Note Products' => CreditNoteProductsModel::count(),
            'Customer Visit' => CustomerVisitModel::count(),
            'Debit Note' => DebitNoteModel::count(),
            'Debit Note Products' => DebitNoteProductsModel::count(),
        ];

        // Build HTML directly
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Stats Overview</title>
<style>
    body { font-family: Arial, sans-serif; margin: 40px auto; max-width: 700px; background: #f9f9f9; }
    table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    th, td { padding: 12px 20px; border: 1px solid #ddd; text-align: left; }
    th { background-color: #007bff; color: white; }
    caption { font-size: 1.5em; margin-bottom: 10px; font-weight: bold; color: #333; }
    tbody tr:hover { background-color: #f1f7ff; }
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

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
