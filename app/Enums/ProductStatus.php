<?php

namespace App\Enums;

enum ProductStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Live = 'live';
    case Rejected = 'rejected';
    case Violation = 'violation';
    case Unpublished = 'unpublished';
}
