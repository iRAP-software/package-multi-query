<?php

namespace iRAP\MultiQuery;

enum TransactionStatus: string
{
    case NOT_APPLICABLE = "NOT_APPLICABLE";
    case SUCCEEDED = "SUCCEEDED";
    case FAILED = "FAILED";
}
