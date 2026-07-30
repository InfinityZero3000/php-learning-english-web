<?php

namespace App\Services\Import;

/**
 * Finite set of reasons an import item can fail. Used instead of raw
 * exception messages so the admin UI/API never surfaces internal error
 * detail (stack traces, SQL, provider response bodies) to end users.
 */
enum ImportErrorCode: string
{
    case ProviderTimeout = 'provider_timeout';
    case ProviderRejected = 'provider_rejected';
    case PayloadInvalid = 'payload_invalid';
    case WriteFailed = 'write_failed';
}
