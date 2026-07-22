<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->when($request->action,     fn($q) => $q->where('action', $request->action))
            ->when($request->user_id,    fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->model_type, fn($q) => $q->where('model_type', $request->model_type))
            ->when($request->date_from,  fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,    fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->search,     fn($q) =>
                $q->where('description', 'like', "%{$request->search}%")
                  ->orWhere('user_name', 'like', "%{$request->search}%")
            );

        return response()->json($query->latest('created_at')->paginate(50));
    }

    public function show(AuditLog $auditLog)
    {
        return response()->json($auditLog->load('user'));
    }
}