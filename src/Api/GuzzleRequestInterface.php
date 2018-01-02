<?php

namespace Trustly\Api;

use GuzzleHttp\Client;

/**
 * GuzzleRequestInterface interface.
 */
interface GuzzleRequestInterface
{
    /**
     * @param Client $guzzle
     *
     * @return $this
     */
    public function setGuzzle(Client $guzzle);
}
