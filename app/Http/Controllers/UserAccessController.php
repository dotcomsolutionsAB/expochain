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
    public function show($id)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        $row = UserAccessModel::where('id', $id)
            ->where('company_id', $auth->company_id)
            ->first();

        if (!$row) {
            return response()->json(['code'=>404,'success'=>false,'message'=>'Not found'], 404);
        }

        return response()->json(['code'=>200,'success'=>true,'data'=>$row], 200);
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
    public function destroy($id)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
        }

        $row = UserAccessModel::where('id', $id)
            ->where('company_id', $auth->company_id)
            ->first();

        if (!$row) {
            return response()->json(['code'=>404,'success'=>false,'message'=>'Not found'], 404);
        }

        $row->delete();

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'Access deleted'
        ], 200);
    }
}