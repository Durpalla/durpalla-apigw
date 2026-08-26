<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Services\MerchantDeskReportService;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MerchantReportController extends Controller
{
    use ResolvesMerchantOwner;

    /**
     * Merchant Desk Pro slugs plus legacy title-case types.
     *
     * @var list<string>
     */
    private const REPORT_TYPES = [
        'sales',
        'revenue',
        'occupancy',
        'settlement',
        'summary',
        'bookings',
        'customers',
        'units',
        'cancellations',
        'payments',
        'staff_performance',
        'sales_channels',
    ];

    public function __construct(
        private readonly MerchantDeskReportService $reportService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->validateReportQuery($request);

        $from = $request->input('from');
        $to = $request->input('to');
        $data = $this->reportService->buildReport($ownerId, $from, $to);
        $data['report_type'] = $request->input('type', 'sales');

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $data = $this->reportService->buildDashboardStats($ownerId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function export(Request $request): StreamedResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->validateReportQuery($request);

        return $this->reportService->streamCsv(
            $ownerId,
            $request->input('from'),
            $request->input('to'),
            (string) $request->input('type', 'sales')
        );
    }

    private function validateReportQuery(Request $request): void
    {
        if ($request->filled('type')) {
            $request->merge(['type' => strtolower(trim((string) $request->input('type')))]);
        }

        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'type' => ['nullable', 'string', Rule::in(self::REPORT_TYPES)],
        ]);
    }
}
