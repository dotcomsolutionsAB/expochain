<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserAccessModel;
use App\Models\User;

class UserAccessController extends Controller
{
    /**
     * List (same company scope) with filters + pagination.
     */
    public function index(Request $request)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        $companyId = (int) $auth->company_id;

        $userId  = $request->input('user_id');     // optional
        $module  = $request->input('module');      // optional
        $func    = $request->input('function');    // optional
        $limit   = (int) $request->input('limit', 20);
        $offset  = (int) $request->input('offset', 0);

        $q = UserAccessModel::where('company_id', $companyId);

        if ($userId) { $q->where('user_id', (int) $userId); }
        if ($module) { $q->where('module', $module); }
        if ($func)   { $q->where('function', $func); }

        $total = $q->count();
        $rows  = $q->offset($offset)->limit($limit)->orderBy('id','desc')->get();

        if ($rows->isEmpty()) {
            return response()->json(['code'=>404,'success'=>false,'message'=>'No records found'], 404);
        }

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'Fetched successfully',
            'data'=>$rows,
            'count'=>$rows->count(),
            'total'=>$total
        ], 200);
    }

    /**
     * Create (company_id comes from token).
     * Enforces uniqueness per (company_id, user_id, module, function).
     */
    public function store(Request $request)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        $companyId = (int) $auth->company_id;

        $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'module'   => 'required|string|max:100',
            'function' => 'required|string|max:100',
        ]);

        // Ensure target user is in the same company
        $targetUser = User::where('id', $request->input('user_id'))
                          ->where('company_id', $companyId)
                          ->first();
        if (!$targetUser) {
            return response()->json([
                'code'=>422,
                'success'=>false,
                'message'=>'User is not in your company.'
            ], 422);
        }

        // Upfront duplicate check (unique composite key also guards this)
        $exists = UserAccessModel::where([
            'company_id' => $companyId,
            'user_id'    => $request->input('user_id'),
            'module'     => $request->input('module'),
            'function'   => $request->input('function'),
        ])->exists();

        if ($exists) {
            return response()->json([
                'code'=>409,
                'success'=>false,
                'message'=>'Access already exists for this user/module/function.'
            ], 409);
        }

        $item = UserAccessModel::create([
            'company_id' => $companyId,
            'user_id'    => (int) $request->input('user_id'),
            'module'     => $request->input('module'),
            'function'   => $request->input('function'),
        ]);

        return response()->json([
            'code'=>201,
            'success'=>true,
            'message'=>'Access created',
            'data'=>$item
        ], 201);
    }

    /**
     * Show one (company scoped)
     */
    public function permissionsByUser(Request $request, int $userId)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        $companyId = (int) $auth->company_id;

        // Ensure target user belongs to same company
        $target = User::where('id', $userId)->where('company_id', $companyId)->first();
        if (!$target) {
            return response()->json([
                'code'=>404,'success'=>false,'message'=>'User not found or not part of your company.'
            ], 404);
        }

        // Canonical function set (lowercase slugs)
        $functions = ['create','view','edit','delete','export','import','print'];

        // Canonical module set (use whatever slugs you prefer to expose)
        $modules = [
            'products',
            'clients',
            'suppliers',
            'quotations',
            'sales_order',
            'sales_invoice',
            'lot_info',
            'purchase_order',
            'purchase_invoice',
            'stock_transfer',
            'pdf_template',
            'test_certificate',
            'physical_stock',
            'credit_note',
            'debit_note',
        ];

        // Helper normalizer (DB may contain spaces/case)
        $normalize = fn(string $s) => preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', strtolower(trim($s))));

        // Start with all false
        $result = [];
        foreach ($modules as $m) {
            $result[$m] = array_fill_keys($functions, false);
        }

        // Fetch all access rows for this user & company
        $rows = UserAccessModel::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->get(['module','function']);

        // Turn on flags where a record exists
        foreach ($rows as $row) {
            $mod = $normalize((string)$row->module);
            $fun = $normalize((string)$row->function);

            // Map non-canonical synonyms if any (e.g., "update" => "edit")
            if ($fun === 'update') $fun = 'edit';

            if (isset($result[$mod]) && in_array($fun, $functions, true)) {
                $result[$mod][$fun] = true;
            }
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Permissions fetched successfully.',
            'data' => [
                'company_id'  => $companyId,
                'user_id'     => $userId,
                'permissions' => $result,
            ]
        ], 200);
    }

    /**
     * Update (company scoped)
     */
    public function update(Request $request, $id)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'module'   => 'required|string|max:100',
            'function' => 'required|string|max:100',
        ]);

        $companyId = (int) $auth->company_id;

        // Ensure target user in same company
        $targetUser = User::where('id', $request->input('user_id'))
                          ->where('company_id', $companyId)
                          ->first();
        if (!$targetUser) {
            return response()->json(['code'=>422,'success'=>false,'message'=>'User is not in your company.'], 422);
        }

        $row = UserAccessModel::where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$row) {
            return response()->json(['code'=>404,'success'=>false,'message'=>'Not found'], 404);
        }

        // Duplicate guard excluding current id
        $dup = UserAccessModel::where('company_id', $companyId)
            ->where('user_id', (int)$request->input('user_id'))
            ->where('module', $request->input('module'))
            ->where('function', $request->input('function'))
            ->where('id', '!=', $id)
            ->exists();

        if ($dup) {
            return response()->json([
                'code'=>409,
                'success'=>false,
                'message'=>'Another access with same user/module/function exists.'
            ], 409);
        }

        $row->update([
            'user_id'  => (int) $request->input('user_id'),
            'module'   => $request->input('module'),
            'function' => $request->input('function'),
        ]);

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'Access updated',
            'data'=>$row
        ], 200);
    }

    /**
     * Delete (company scoped)
     */
    public function destroy(Request $request)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        // Validate inputs
        $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'module'   => 'required|string|max:100',
            'function' => 'required|string|max:100',
        ]);

        $companyId = (int) $auth->company_id;
        $userId    = (int) $request->input('user_id');
        $module    = trim((string) $request->input('module'));
        $function  = trim((string) $request->input('function'));

        // Ensure target user is in the same company
        $targetUser = User::where('id', $userId)
            ->where('company_id', $companyId)
            ->first();

        if (!$targetUser) {
            return response()->json([
                'code'=>422,'success'=>false,'message'=>'User is not in your company.'
            ], 422);
        }

        // Normalization helpers (case-insensitive match; map update->edit)
        $norm = fn(string $s) => strtolower(trim($s));
        $moduleNorm   = $norm($module);
        $functionNorm = $norm($function);
        if ($functionNorm === 'update') $functionNorm = 'edit';

        // Delete row(s) if exist (case-insensitive)
        $deleted = UserAccessModel::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereRaw('LOWER(`module`) = ?', [$moduleNorm])
            ->whereRaw('LOWER(`function`) = ?', [$functionNorm])
            ->delete();

        if ($deleted < 1) {
            return response()->json([
                'code'=>404,
                'success'=>false,
                'message'=>'No matching access record found to delete.'
            ], 404);
        }

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'Access deleted',
            'deleted'=> $deleted
        ], 200);
    }
}