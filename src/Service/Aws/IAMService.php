<?php

/*
 * This file is part of Tactic Media AWS Toolkit.
 *
 * (c) 2020 Tactic Media Pty Ltd <support@tacticmedia.com.au>
 *
 * See LICENSE for more details.
 */

namespace App\Service\Aws;

use Aws\Iam\IamClient;
use Aws\Result;
use Aws\Sdk;

class IAMService extends AwsService
{
    private IamClient $iamClient;

    public function __construct(Sdk $aws)
    {
        parent::__construct($aws);

        $this->iamClient = $aws->createIam();
    }

    public function getClient(): IamClient
    {
        return $this->iamClient;
    }

    public function createUser(string $username, ?string $defaultPolicyToAttach = null)
    {
        $this->iamClient->createUser([
            'UserName' => $username,
        ]);

        $accessKeyResult = $this->iamClient->createAccessKey([
            'UserName' => $username,
        ]);

        $this->iamClient->attachUserPolicy([
            'UserName' => $username,
            'PolicyArn' => 'arn:aws:iam::'.$this->getAccountId().':policy/'.$defaultPolicyToAttach,
        ]);

        return [
            'UserName' => $accessKeyResult->search('AccessKey.UserName'),
            'AccessKeyId' => $accessKeyResult->search('AccessKey.AccessKeyId'),
            'SecretAccessKey' => $accessKeyResult->search('AccessKey.SecretAccessKey'),
        ];
    }

    public function creates3BucketWriteAccessPolicy(string $bucketName): Result
    {
        $policy = '
            {
              "Version": "2012-10-17",
              "Statement": [
                  {
                      "Effect": "Allow",
                      "Action": [
                          "s3:PutBucketAcl",
                          "s3:ListBucket",
                          "s3:GetBucketAcl",
                          "s3:GetBucketLocation"
                      ],
                      "Resource": "arn:aws:s3:::'.$bucketName.'"
                  },
                  {
                      "Effect": "Allow",
                      "Action": [
                          "s3:PutObject",
                          "s3:GetObjectAcl",
                          "s3:GetObject",
                          "s3:DeleteObject",
                          "s3:PutObjectAcl"
                      ],
                      "Resource": "arn:aws:s3:::'.$bucketName.'/*"
                  }
              ]
            }
        ';

        return $this->iamClient->createPolicy([
            // Transforms hyphen-case to HyphenCase
            'PolicyName' => implode(array_map('ucfirst', explode('-', $bucketName))).'WriteAccess',
            'PolicyDocument' => trim($policy),
        ]);
    }
}
