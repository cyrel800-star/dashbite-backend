<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function approve(int $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'user_not_found'], 404);
        }

        $user->update(['account_status' => AccountStatus::Approved]);

        return response()->json([
            'message' => 'Account approved.',
            'data'    => ['id' => $user->id, 'account_status' => $user->account_status->value],
        ]);
    }
}