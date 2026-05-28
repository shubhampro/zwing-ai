<?php

namespace App\Services;

use App\Models\StockReconErpLog;
use App\Models\StockReconSession;
use App\Models\StockReconZwingLog;
use App\Support\Sprefcode;

class StockReconLogDetailService
{
    /**
     * @return array{
     *     has_zwing_logs: bool,
     *     has_erp_logs: bool,
     *     matched: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     *     mismatch: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     * }
     */
    public function forSku(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $batchNo,
        string $sprefcode,
    ): array {
        $hasZwingLogs = $session->zwing_log_file_name !== null;
        $hasErpLogs = $session->erp_log_file_name !== null;

        $zwingRows = $hasZwingLogs
            ? $this->fetchLogs(StockReconZwingLog::class, $session->id, $siteCode, $icode, $batchNo, $sprefcode)
            : [];

        $erpRows = $hasErpLogs
            ? $this->fetchLogs(StockReconErpLog::class, $session->id, $siteCode, $icode, $batchNo, $sprefcode)
            : [];

        $erpKeys = [];
        foreach ($erpRows as $row) {
            $erpKeys[$this->pairKey($row['doc_no'], $row['qty'])] = true;
        }

        $zwingKeys = [];
        foreach ($zwingRows as $row) {
            $zwingKeys[$this->pairKey($row['doc_no'], $row['qty'])] = true;
        }

        $matchedZwing = [];
        $matchedErp = [];
        $mismatchZwing = [];
        $mismatchErp = [];

        foreach ($zwingRows as $row) {
            $key = $this->pairKey($row['doc_no'], $row['qty']);
            if (isset($erpKeys[$key])) {
                $matchedZwing[] = $row;
            } else {
                $mismatchZwing[] = $row;
            }
        }

        foreach ($erpRows as $row) {
            $key = $this->pairKey($row['doc_no'], $row['qty']);
            if (isset($zwingKeys[$key])) {
                $matchedErp[] = $row;
            } else {
                $mismatchErp[] = $row;
            }
        }

        return [
            'has_zwing_logs' => $hasZwingLogs,
            'has_erp_logs' => $hasErpLogs,
            'matched' => [
                'zwing' => $matchedZwing,
                'erp' => $matchedErp,
            ],
            'mismatch' => [
                'zwing' => $mismatchZwing,
                'erp' => $mismatchErp,
            ],
        ];
    }

    /**
     * @param  class-string<StockReconZwingLog|StockReconErpLog>  $modelClass
     * @return list<array{id: int, doc_no: string, qty: float, enttype: string}>
     */
    private function fetchLogs(
        string $modelClass,
        int $sessionId,
        string $siteCode,
        string $icode,
        string $batchNo,
        string $sprefcode,
    ): array {
        return $modelClass::query()
            ->where('stock_recon_session_id', $sessionId)
            ->where('site_code', $siteCode)
            ->where('icode', $icode)
            ->where('batch_no', $batchNo)
            ->orderBy('doc_no')
            ->orderBy('qty')
            ->get(['id', 'doc_no', 'qty', 'enttype', 'sprefcode'])
            ->filter(fn ($row) => Sprefcode::matches((string) $row->sprefcode, $sprefcode))
            ->map(fn ($row) => [
                'id' => $row->id,
                'doc_no' => trim($row->doc_no),
                'qty' => (float) $row->qty,
                'enttype' => $row->enttype,
            ])
            ->values()
            ->all();
    }

    private function pairKey(string $docNo, float $qty): string
    {
        return trim($docNo).'|'.number_format($qty, 4, '.', '');
    }
}
