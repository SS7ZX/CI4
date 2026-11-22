<?php

declare(strict_types=1);

namespace Config;

use App\Libraries\CvAi\CurlCvAiHttpClient;
use App\Libraries\CvAi\CvAiDraftGeneratorInterface;
use App\Libraries\CvAi\CvAiEventStreamInterface;
use App\Libraries\CvAi\PhpCvAiEventStream;
use App\Libraries\CvAi\OllamaCvAiDraftGenerator;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function cvAiEventStream(bool $getShared = true): CvAiEventStreamInterface
    {
        if ($getShared) {
            return static::getSharedInstance('cvAiEventStream');
        }

        return new PhpCvAiEventStream();
    }

    public static function cvAiDraftGenerator(bool $getShared = true): CvAiDraftGeneratorInterface
    {
        if ($getShared) {
            return static::getSharedInstance('cvAiDraftGenerator');
        }

        return new OllamaCvAiDraftGenerator(config(CvAi::class), new CurlCvAiHttpClient());
    }
}
