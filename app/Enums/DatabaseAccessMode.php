<?php

namespace App\Enums;

enum DatabaseAccessMode: string
{
    case Read = 'read';
    case Write = 'write';
}
