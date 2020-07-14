<?php

/*
 * This file is part of Tactic Media AWS Toolkit.
 *
 * (c) 2020 Tactic Media Pty Ltd <support@tacticmedia.com.au>
 *
 * See LICENSE for more details.
 */

namespace App\Service\Aws;

use Aws\Sdk;

abstract class AwsService
{
    protected Sdk $aws;

    public function __construct(Sdk $aws)
    {
        $this->aws = $aws;
    }

    public function getAccountId(): int
    {
        static $accountId;

        if (!$accountId) {
            $accountId = (int) $this->aws->createSts()->getCallerIdentity()->get('Account');
        }

        return $accountId;
    }
}
