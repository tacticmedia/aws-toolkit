# Tactic Media AWS Toolkit

This is a Symfony console application taking the fuss out of repetitive task related to managing AWS resources.

## How to use AWS Toolkit

**Requirements:**

1. PHP 7.4
2. Composer
3. Admin-level AWS API key

### Installation & usage

1. Check out this repository and open it in your terminal
2. Run `composer install`
3. Create `.env.local` and override any settings you don't like - hint: `AWS_KEY` and `AWS_SECRET` will
4. Run `bin/console aws` to see the list of available commands.

## Available commands

### aws:create:hosting

Creates an S3 bucket and configures a CloudFront distribution to serve it. Optionally creates a new user with
write permission for the bucket.

All you have to do to start serving your static website is to point your domain name at the CF distribution's domain name.

Additional resources: [AWS documentation](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/CNAMEs.html)

## Do you need help?

This package is a showcase of what [Tactic Media](https://tacticmedia.com.au) can do for you. We specialise in software development, DevOps and general IT consultations.

Check out [our contact page](https://tacticmedia.com.au/contact.html) for ways to get in touch or have a look at the rest of [Tactic Media tools hosted on GitHub](https://github.com/tacticmedia).
