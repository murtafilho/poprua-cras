<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreClientLogRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ClientLogController extends Controller
{
    /**
     * Recebe logs do cliente (mobile/browser) e grava no canal 'client'.
     * POST /api/client-logs
     */
    public function store(StoreClientLogRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userAgent = $request->userAgent();
        $userId = $request->user()->id ?? 'anon';
        $ip = $request->ip();

        $logger = Log::channel('client');

        foreach ($validated['logs'] as $entry) {
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

        return response()->json(['received' => count($validated['logs'])]);
    }
}
