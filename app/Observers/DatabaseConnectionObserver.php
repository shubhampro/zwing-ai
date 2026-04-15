<?php

namespace App\Observers;

use App\Models\DatabaseConnection;
use App\Services\DatabaseConnectionRegistrar;

class DatabaseConnectionObserver
{
    public function saved(DatabaseConnection $databaseConnection): void
    {
        DatabaseConnectionRegistrar::register();
    }

    public function deleted(DatabaseConnection $databaseConnection): void
    {
        DatabaseConnectionRegistrar::register();
    }
}
