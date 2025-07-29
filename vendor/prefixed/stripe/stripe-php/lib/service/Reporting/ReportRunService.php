<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Reporting;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @license MIT
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */
class ReportRunService extends \StoreEngine\Stripe\Service\AbstractService
{
    /**
     * Returns a list of Report Runs, with the most recent appearing first.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Collection<\Stripe\Reporting\ReportRun>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/reporting/report_runs', $params, $opts);
    }

    /**
     * Creates a new object and begin running the report. (Certain report types require
     * a <a href="https://stripe.com/docs/keys#test-live-modes">live-mode API key</a>.).
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Reporting\ReportRun
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/reporting/report_runs', $params, $opts);
    }

    /**
     * Retrieves the details of an existing Report Run.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\StoreEngine\Stripe\Util\RequestOptions $opts
     *
     * @throws \StoreEngine\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \StoreEngine\Stripe\Reporting\ReportRun
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/reporting/report_runs/%s', $id), $params, $opts);
    }
}
