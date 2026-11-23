<?php

namespace App\Support;

use App\Models\Company;

/**
 * Read-side companion to ResolveCompanyDomain: lets Blade views and other code ask "did
 * this request's Host resolve to one of our companies' custom domains?" without having
 * the Request object threaded through them.
 *
 * Uses `app('request')` rather than constructor-injecting Illuminate\Http\Request: this
 * is a static-call helper (DomainContext::current()), not a container-resolved service,
 * so there is no constructor for the framework to inject into — resolving the current
 * request out of the container at call time is the only way a plain static method can
 * reach it, and it always returns the one true "current" request for this HTTP lifecycle.
 */
class DomainContext
{
    public static function current(): ?Company
    {
        $company = app('request')->attributes->get('resolvedDomainCompany');

        return $company instanceof Company ? $company : null;
    }
}
