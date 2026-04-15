<?php

namespace App\Enums;

enum DatabaseDriver: string
{
    case Mysql = 'mysql';
    case Pgsql = 'pgsql';
    case Mongodb = 'mongodb';
}
