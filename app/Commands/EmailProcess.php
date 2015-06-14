<?php

namespace AbuseIO\Commands;

Use Illuminate\Queue\SerializesModels;
Use Illuminate\Queue\InteractsWithQueue;
Use Illuminate\Contracts\Bus\SelfHandling;
Use Illuminate\Contracts\Queue\ShouldQueue;
Use Illuminate\Filesystem\Filesystem;
use League\Flysystem\Exception;
Use PhpMimeMailParser\Parser as MimeParser;
Use AbuseIO\Parsers\Factory as GetParser;
Use AbuseIO\Commands\EventsValidate;
Use AbuseIO\Commands\EventsSave;
Use Config;
Use Log;

class EmailProcess extends Command implements SelfHandling//, ShouldQueue
{
    //use InteractsWithQueue, SerializesModels;

    public $filename;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Queue command into named tube.
     *
     * @return void
     */
    public function queue($queue, $command)
    {
        $queue->pushOn('emails', $command);
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {

        Log::info(get_class($this).': Queued worker is starting the processing of email file: ' . $this->filename);

        $filesystem = new Filesystem;
        $rawEmail = $filesystem->get($this->filename);

        $parsedMail = new MimeParser();
        $parsedMail->setText($rawEmail);

        // Sanity checks
        if (empty($parsedMail->getHeader('from')) || empty($parsedMail->getMessageBody())) {

            Log::warning(get_class($this).'Validation failed on: ' . $this->filename);
            $this->exception();

        }

        // Ignore email from our own notification address to prevent mail loops
        if (preg_match('/' . Config::get('main.notifications.from_address') . '/', $parsedMail->getHeader('from'))) {

            Log::warning(get_class($this).'Loop prevention: Ignoring email from self ' . Config::get('main.notifications.from_address'));
            $this->exception();

        }

        // Start with detecting valid ARF e-mail
        $attachments = $parsedMail->getAttachments();
        $arfMail = [ ];

        foreach ($attachments as $attachment) {

            if ($attachment->contentType == 'message/feedback-report') {

                $arfMail['report'] = $attachment->getContent();

            }

            if ($attachment->contentType == 'message/rfc822') {

                $arfMail['evidence'] = $attachment->getContent();

            }

            if ($attachment->contentType == 'text/plain') {

                $arfMail['message'] = $attachment->getContent();

            }

        }

        // If we do not have a complete e-mail, then we empty the perhaps partially filled arfMail
        if (!isset($arfMail['report']) || !isset($arfMail['evidence']) || !isset($arfMail['message'])) {

            $arfMail = [ ];

        }

        // Asking GetParser for an object based on mappings, or die trying
        $parser = GetParser::object($parsedMail, $arfMail);

        $result = false;
        $events = false;

        if ($parser !== false) {

            $result = $parser->parse();

        } else {

            Log::error(get_class($this).': Unable to handle message from: ' . $parsedMail->getHeader('from') . ' with subject: ' . $parsedMail->getHeader('subject'));
            $this->exception();

        }

        if ($result !== false && $result['errorStatus'] !== true) {

            Log::info(get_class($parser).': Parser as ended without errors');
            $events = $result['data'];

        } else {

            Log::error(get_class($parser).': Parser as ended with errors ! : ' . $result['errorMessage']);
            $this->exception();

        }

        // call validater

        $validator = new EventsValidate($events);
        $return = $validator->handle();

        if ($return['errorStatus'] === false) {

            Log::error(get_class($validator).': Validator as ended with errors ! : ' . $result['errorMessage']);
            $this->exception();

        } else {

            Log::info(get_class($validator).': Validator as ended without errors');

        }

        // call saver

        $saver = new EventsSave($events);
        $return = $saver->handle();

        if ($return['errorStatus'] === false) {

            Log::error(get_class($saver).': Saver as ended with errors ! : ' . $result['errorMessage']);
            $this->exception();

        } else {

            Log::info(get_class($saver).': Saver as ended without errors');

        }

    }

    /**
     * We've hit a snag, so we are gracefully killing ourselves after we contact the admin about it.
     *
     * @return mixed
     */
    protected function exception()
    {
        // we have $this->filename and $this->rawMail
        // and this Config::get('main.emailparser.fallback_mail')
                
        Log::error(get_class($this).': Ending with errors. The received e-mail will be deleted from archive and bounced to the admin for investigation');
        // TODO: send the rawmail back to admin and delete file

        dd();

        Mail::queueOn(
            'FailedProcessNotifications',
            'emails.bounce', '', function($message) {

                $message->from(Config::get('main.notifications.from_address'), 'AbuseIO EmailProcess');

                $message->to(Config::get('main.emailparser.fallback_mail'));

                $message->attach($this->filename, ['as' => 'failed_message.eml', 'mime' => 'message/rfc822']);;

            }
        );

    }

}
