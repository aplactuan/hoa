<?php

namespace App\Enums;

enum PenaltyType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
