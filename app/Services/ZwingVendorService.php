<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ZwingVendorService
{
    /**
     * @return list<array{id: int, name: string, ba_code: string, db_name: string}>
     */
    public function list(): array
    {
        SshTunnelManager::ensureMysqlOpen();

        return DB::connection('mysql_ssh')
            ->table('vendor')
            ->where('deleted', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'client_id', 'db_name'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'ba_code' => (string) ($row->client_id ?? ''),
                'db_name' => (string) ($row->db_name ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, ba_code: string, db_name: string}|null
     */
    public function find(int $vendorId): ?array
    {
        SshTunnelManager::ensureMysqlOpen();

        $row = DB::connection('mysql_ssh')
            ->table('vendor')
            ->where('deleted', 0)
            ->where('id', $vendorId)
            ->first(['id', 'name', 'client_id', 'db_name']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'ba_code' => (string) ($row->client_id ?? ''),
            'db_name' => (string) ($row->db_name ?? ''),
        ];
    }
}
