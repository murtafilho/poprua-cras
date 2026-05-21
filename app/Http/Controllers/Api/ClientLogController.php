<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientLogController extends Controller
{
    /**
     * Recebe logs do cliente (mobile/browser) e grava no canal 'client'.
     * POST /api/client-logs
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'logs' => 'required|array|max:50',
            'logs.*.level' => 'required|in:debug,info,warn,error',
            'logs.*.message' => 'required|string|max:1000',
            'logs.*.context' => 'nullable|array',
            'logs.*.timestamp' => 'nullable|numeric',
        ]);

        $userAgent = $request->userAgent();
        $userId = $request->user()?->id ?? 'anon';
        $ip = $request->ip();

        $logger = Log::channel('client');

        foreach ($request->logs as $entry) {
            $level = $entry['level'] === 'warn' ? 'warning' : $entry['level'];
            $context = array_merge(
                $entry['context'] ?? [],
                [
                    'user_id' => $userId,
                    'ip' => $ip,
                    'ua' => $userAgent,
                    'client_ts' => $entry['timestamp'] ?? null,
                ]
            );

            $logger->$level($entry['message'], $context);
        }

        return response()->json(['received' => count($request->logs)]);
    }
}
