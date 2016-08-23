<?php namespace CoasterCms\Helpers\Cms;

use CoasterCms\Exceptions\CmsPageException;
use CoasterCms\Libraries\Builder\PageBuilder;
use Illuminate\Mail\Message;
use Mail;
use Validator;
use View;

class Email
{

    public static function sendFromFormData($templates, $formData, $subject, $to = null, $from = null)
    {
        // get email details to send to
        $emailDetails = [
            'subject' => $subject,
            'to' => $to ?: config('coaster::site.email'),
            'from' => $from ?: config('coaster::site.email'),
            'userEmail' => null
        ];

        $emailCheck = Validator::make($emailDetails, ['to' => 'email|required', 'from' => 'email|required']);
        if ($emailCheck->passes()) {

            // split to addresses
            if (strpos($emailDetails['to'], ',') !== false) {
                $emailDetails['to'] = explode(',', $emailDetails['to']);
            }

            // get templates
            $emailsViews = ['themes.' . PageBuilder::getData('theme') . '.emails.'];
            foreach ($templates as $template) {
                $emailsViews[] = $emailsViews[0] . $template . '.';
            }

            $sendTemplate = null;
            $replyTemplate = null;
            foreach ($emailsViews as $emailsView) {
                if (!$sendTemplate && View::exists($emailsView . 'default')) {
                    $sendTemplate = $emailsView . 'default';
                }
                if (!$replyTemplate && View::exists($emailsView . 'reply')) {
                    $replyTemplate = $emailsView . 'reply';
                }
            }
            if (!$sendTemplate) {
                throw new CmsPageException('No default email template', 500);
            }
            $replyTemplate = $replyTemplate ?: $sendTemplate;

            // generate body
            $body = '';
            foreach ($formData as $field => $value) {
                if (is_array($value)) {
                    $value = implode(", ", $value);
                }
                if (strpos($value, "\r\n") !== false) {
                    $value = "<br />" . str_replace("\r\n", "<br />", $value);
                }
                $body .= ucwords(str_replace('_', ' ', $field)) . ": $value <br />";
                if (stristr($field, 'email') !== false) {
                    $emailDetails['userEmail'] = $value;
                }
            }

            Mail::send($sendTemplate, ['body' => $body, 'formData' => $formData, 'form_data' => $formData], function (Message $message) use ($emailDetails) {
                if ($emailDetails['userEmail']) {
                    $message->replyTo($emailDetails['reply']);
                }
                $message->from($emailDetails['from']);
                $message->to($emailDetails['to']);
                $message->subject($emailDetails['subject']);
            });

            if ($emailDetails['userEmail']) {
                Mail::send($replyTemplate, ['body' => $body, 'formData' => $formData, 'form_data' => $formData], function (Message $message) use ($emailDetails) {
                    $message->to($emailDetails['from']);
                    $message->from($emailDetails['userEmail']);
                    $message->subject($emailDetails['subject']);
                });
            }

        }

        return Mail::failures();
    }

}