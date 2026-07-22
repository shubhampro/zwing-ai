<?php

namespace App\Support;

/**
 * Serial queue for all remote DB queries (SSH / org MySQL / ERP Postgres).
 * Horizon runs this with maxProcesses=1 via supervisor-external-query.
 */
final class ExternalQueryQueue
{
    public const NAME = 'external-query';
}
