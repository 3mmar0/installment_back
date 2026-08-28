<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Pending = 'pending';
    case Replied = 'replied';
    case Closed = 'closed';
}
