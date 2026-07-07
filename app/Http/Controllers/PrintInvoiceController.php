<?php

namespace App\Http\Controllers;

use App\Services\PrintInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrintInvoiceController extends Controller
{
    /**
     * @return array{
     *     url: string,
     *     tenant_id: string,
     *     token: string,
     *     body: array<string, mixed>
     * }
     */
    private static function defaultPayload(): array
    {
        return [
            'url' => 'https://aks-prod.api.gozwing.com/pos/print/invoice',
            'tenant_id' => '151',
            'token' => '',
            'body' => [
                'invoiceId' => 'RA03592600034',
                'orderId' => 'RA03592600034',
                'storeId' => 1,
                'vId' => 151,
                'terminalId' => 2679,
                'transaction' => 'INVOICE',
                'transFrom' => 'CLOUD_TAB_WEB',
                'trim' => true,
                'timezone' => '+05:30',
                'trans_from' => 'CLOUD_TAB_WEB',
                'udidtoken' => '',
                'terminal_id' => 2679,
                'session_id' => 306,
            ],
        ];
    }

    public function index(): Response
    {
        abort_if(auth()->user() === null, 403);

        $defaults = self::defaultPayload();

        return Inertia::render('print-invoice/index', [
            'defaultPayload' => [
                ...$defaults,
                'bodyJson' => json_encode($defaults['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
        ]);
    }

    public function preview(Request $request, PrintInvoiceService $printInvoiceService): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'tenant_id' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:4096'],
            'body' => ['required', 'array'],
        ]);

        $response = $printInvoiceService->printInvoice(
            $validated['url'],
            $validated['tenant_id'],
            $validated['token'],
            $validated['body'],
        );

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice preview from API.',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'html' => $response->body(),
            'status' => $response->status(),
        ]);
    }
}
