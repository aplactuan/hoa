<?php

namespace App\Enums;

enum OwnerChangeStatus: string
{
    case Pending = 'Pending';
    case Approved = 'Approved';
    case Declined = 'Declined';
    case Cancelled = 'Cancelled';
}
