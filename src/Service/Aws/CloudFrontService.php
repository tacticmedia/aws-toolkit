<?php

/*
 * This file is part of Tactic Media AWS Toolkit.
 *
 * (c) 2020 Tactic Media Pty Ltd <support@tacticmedia.com.au>
 *
 * See LICENSE for more details.
 */

namespace App\Service\Aws;

use Aws\CloudFront\CloudFrontClient;
use Aws\Result;
use Aws\Sdk;

class CloudFrontService extends AwsService
{
    private CloudFrontClient $cloudFrontClient;

    public function __construct(Sdk $aws)
    {
        parent::__construct($aws);

        $this->cloudFrontClient = $aws->createCloudFront();
    }

    public function getClient(): CloudFrontClient
    {
        return $this->cloudFrontClient;
    }

    public function createS3BucketDistribution(string $s3BucketURL, string $region, ?string $hostname = null): Result
    {
        // Get the bucket name without S3 suffix
        $originName = preg_replace('/\.s3.amazonaws.com/', '', $s3BucketURL);
        // Make sure to use the local bucket URL (shortens how long does it take before things start working)
        $s3BucketURL = preg_replace('/\.s3.amazonaws.com/', '.s3.'.$region.'.amazonaws.com', $s3BucketURL);

        $result = $this->cloudFrontClient->createCloudFrontOriginAccessIdentity([
            'CloudFrontOriginAccessIdentityConfig' => [
                'CallerReference' => rand(0, PHP_INT_MAX),
                'Comment' => 'access-identity-'.$s3BucketURL,
            ],
        ]);

        $originAccessIdentityId = $result['CloudFrontOriginAccessIdentity']['Id'];

        $callerReference = $originName.'-caller';
        $comment = ($hostname ? $hostname.' - ' : '').'Created by Tactic Media AWS Toolkit';
        $defaultCacheBehavior = [
            'AllowedMethods' => [
                'CachedMethods' => [
                    'Items' => ['HEAD', 'GET'],
                    'Quantity' => 2,
                ],
                'Items' => ['HEAD', 'GET'],
                'Quantity' => 2,
            ],
            'Compress' => true,
            'DefaultTTL' => 0,
            'FieldLevelEncryptionId' => '',
            'ForwardedValues' => [
                'Cookies' => [
                    'Forward' => 'none',
                ],
                'Headers' => [
                    'Quantity' => 0,
                ],
                'QueryString' => false,
                'QueryStringCacheKeys' => [
                    'Quantity' => 0,
                ],
            ],
            'LambdaFunctionAssociations' => ['Quantity' => 0],
            'MaxTTL' => 31536000, // 1 year
            'MinTTL' => 0,
            'SmoothStreaming' => false,
            'TargetOriginId' => $originName,
            'TrustedSigners' => [
                'Enabled' => false,
                'Quantity' => 0,
            ],
            'ViewerProtocolPolicy' => 'allow-all',
        ];
        $enabled = true;
        $origin = [
            'Items' => [
                [
                    'DomainName' => $s3BucketURL,
                    'Id' => $originName,
                    'OriginPath' => '',
                    'CustomHeaders' => ['Quantity' => 0],
                    'S3OriginConfig' => [
                        'OriginAccessIdentity' => 'origin-access-identity/cloudfront/'.$originAccessIdentityId,
                    ],
                ],
            ],
            'Quantity' => 1,
        ];

        $distribution = [
            'CallerReference' => $callerReference,
            'Comment' => $comment,
            'DefaultCacheBehavior' => $defaultCacheBehavior,
            'Enabled' => $enabled,
            'DefaultRootObject' => 'index.html',
            'Origins' => $origin,
        ];

        try {
            return $this->cloudFrontClient->createDistribution([
                'DistributionConfig' => $distribution,
            ]);
        } catch (AwsException $e) {
            return 'Error: '.$e['message'];
        }
    }
}
