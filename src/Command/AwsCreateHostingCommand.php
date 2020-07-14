<?php

/*
 * This file is part of Tactic Media AWS Toolkit.
 *
 * (c) 2020 Tactic Media Pty Ltd <support@tacticmedia.com.au>
 *
 * See LICENSE for more details.
 */

namespace App\Command;

use App\Service\Aws\IAMService;
use App\Service\Aws\S3Service;
use Aws\Exception\AwsException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class AwsCreateHostingCommand extends Command
{
    protected static $defaultName = 'aws:create:hosting';

    protected S3Service $S3Service;
    protected IAMService $IAMService;

    public function __construct(string $name = null, S3Service $S3Service, IAMService $IAMService)
    {
        parent::__construct($name);

        $this->s3Service = $S3Service;
        $this->IAMService = $IAMService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Creates a new S3 bucket and configures a CloudFront distribution to serve it.')
            ->addArgument('hostname', InputArgument::REQUIRED, 'Hostname to be associated with your CloudFront distribution.')
            ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'Option description', $_SERVER['AWS_REGION'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->s3Service->createHosting(
                $input->getArgument('hostname'),
                $input->getOption('region'),
            );
        } catch (AwsException $e) {
            $io->error($e->getAwsErrorMessage());

            return 1;
        }

        $io->success('S3 Bucket and CF distribution created successfully.');

        $io->text('CloudFront distribution details');
        $io->definitionList(...array_chunk($result, 1, true));

        $io->note('You still have to configure your DNS, create a SSL certificate in us-west-1 and update the distribution with the domain alias.');

        $question = new ConfirmationQuestion('Create a new IAM user with and set of access keys with access to the new bucket?', true);
        if (!$io->askQuestion($question)) {
            return 0;
        }

        $question = new Question('What should the user name be?', $result['BucketName']);
        $username = $io->askQuestion($question);

        try {
            $result = $this->IAMService->createUser($username, $result['WriteAccessPolicyName']);
        } catch (AwsException $e) {
            $io->error($e->getAwsErrorMessage());

            return 1;
        }

        $io->text('IAM user details');
        $io->definitionList(...array_chunk($result, 1, true));

        $io->warning('Make sure to note the SecretAccessKey now, it is not possible to retrieve it past this point.');

        return 0;
    }
}
