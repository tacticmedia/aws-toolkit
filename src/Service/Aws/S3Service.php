<?php

/*
 * This file is part of Tactic Media AWS Toolkit.
 *
 * (c) 2020 Tactic Media Pty Ltd <support@tacticmedia.com.au>
 *
 * See LICENSE for more details.
 */

namespace App\Service\Aws;

use Aws\S3\S3Client;
use Aws\Sdk;
use Symfony\Component\String\ByteString;
use Symfony\Component\String\Slugger\AsciiSlugger;

class S3Service extends AwsService
{
    protected CloudFrontService $cloudFrontService;
    protected IAMService $iamService;

    protected S3Client $s3Client;

    public function __construct(Sdk $aws, CloudFrontService $cloudFrontService, IAMService $iamService)
    {
        parent::__construct($aws);

        $this->cloudFrontService = $cloudFrontService;
        $this->iamService = $iamService;
        $this->s3Client = $aws->createS3();
    }

    public function createHosting(string $hostname, ?string $region = null): array
    {
        $slugger = new AsciiSlugger();
        $bucketName = $slugger->slug($hostname).'-'.strtolower(ByteString::fromRandom(8));

        $result = $this->s3Client->createBucket([
            'Bucket' => $bucketName,
        ]);

        $s3BucketLocation = parse_url($result['Location'], PHP_URL_HOST);

        $this->s3Client->putObject([
            'Bucket' => $bucketName,
            'Key' => 'index.html',
            'Body' => 'Hello World! This is '.$s3BucketLocation,
            'ContentType' => 'text/html',
        ]);

        $this->s3Client->putObject([
            'Bucket' => $bucketName,
            'Key' => 'error.html',
            'Body' => 'Error.',
            'ContentType' => 'text/html',
        ]);

        $createDistributionResult = $this->cloudFrontService->createS3BucketDistribution($s3BucketLocation, $region, $hostname);

        $oai = preg_replace('~origin-access-identity/cloudfront/~', '', $createDistributionResult->search('Distribution.DistributionConfig.Origins.Items[0].S3OriginConfig.OriginAccessIdentity'));

        $s3CanonicalUserId = $this->cloudFrontService->getClient()->getCloudFrontOriginAccessIdentity(['Id' => $oai])->search('CloudFrontOriginAccessIdentity.S3CanonicalUserId');

        $policy = '{
            "Version": "2008-10-17",
            "Id": "PolicyForCloudFrontPrivateContent",
            "Statement": [
                {
                    "Sid": "1",
                    "Effect": "Allow",
                    "Principal": {
                    "CanonicalUser": "'.$s3CanonicalUserId.'"
                    },
                    "Action": "s3:GetObject",
                    "Resource": "arn:aws:s3:::'.$bucketName.'/*"
                }
            ]
        }';

        $this->s3Client->putBucketPolicy([
            'Bucket' => $bucketName,
            'Policy' => $policy,
        ]);

        $iamPolicyResult = $this->iamService->creates3BucketWriteAccessPolicy($bucketName);

        return [
            'Id' => $createDistributionResult->search('Distribution.Id'),
            'DomainName' => $createDistributionResult->search('Distribution.DomainName'),
            'BucketName' => $bucketName,
            'WriteAccessPolicyName' => $iamPolicyResult->search('Policy.PolicyName'),
        ];
    }
}
