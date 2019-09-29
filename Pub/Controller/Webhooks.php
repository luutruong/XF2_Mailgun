<?php

namespace Truonglv\Mailgun\Pub\Controller;

use XF\Entity\User;
use XF\Pub\Controller\AbstractController;

class Webhooks extends AbstractController
{
    public function actionSpamComplaints()
    {
        $data = $this->getWebhooksData();
        if ($data === null) {
            $this->throwNotAcceptableRequest();
        }

        /** @var User $user */
        $user = $data['user'];

        $userOption = $user->Option;

        $userOption->receive_admin_email = $this->options()->tmi_sc_receiveAdminEmail;
        $userOption->email_on_conversation = $this->options()->tmi_sc_emailConversation;

        $userOption->save();

        $this->logInfo(sprintf(
            'Update email notifications for (%s#%d). Reason: Spam complaints',
            $user->username,
            $user->user_id
        ));

        die('OK');
    }

    public function actionUnsubscribes()
    {
        $data = $this->getWebhooksData();
        if ($data === null) {
            $this->throwNotAcceptableRequest();
        }

        /** @var User $user */
        $user = $data['user'];

        $userOption = $user->Option;

        $userOption->receive_admin_email = false;
        $userOption->email_on_conversation = false;

        $userOption->save();

        $this->logInfo(sprintf(
            'Update email notifications for (%s#%d). Reason: Unsubscribes',
            $user->username,
            $user->user_id
        ));

        die('OK');
    }

    public function actionPermanentFailure()
    {
        $data = $this->getWebhooksData();
        if ($data === null) {
            $this->throwNotAcceptableRequest();
        }

        /** @var User $user */
        $user = $data['user'];

        $user->user_state = 'email_bounce';
        $user->save();

        $this->logInfo(sprintf(
            'Update user_state=email_bounce for (%s#%d). Reason: Permanent Failure',
            $user->username,
            $user->user_id
        ));

        die('OK');
    }

    /**
     * @return array|null
     */
    protected function getWebhooksData()
    {
        if (!$this->request->isPost()) {
            return null;
        }

        $input = strval(file_get_contents('php://input'));
        $json = json_decode($input, true);

        if (!is_array($json) || !isset($json['signature']) || !isset($json['event-data'])) {
            return null;
        }

        $recipient = isset($json['event-data']['recipient'])
            ? $json['event-data']['recipient']
            : null;
        $user = $recipient
            ? $this->em()->findOne('XF:User', ['email' => $recipient])
            : null;
        if (!$user) {
            return null;
        }

        if (!$this->verifyPayload($json)) {
            return null;
        }

        return [
            'data' => $json,
            'user' => $user
        ];
    }

    /**
     * @param string|\Exception $message
     * @return void
     */
    protected function logInfo($message)
    {
        if (!$this->options()->tmi_logWebhooks) {
            return;
        }

        if ($message instanceof \Exception) {
            $this->app->logException($message, false, '[tl] Mailgun Integration: [Info] ');
        } else {
            \XF::logError("[tl] Mailgun Integration: [Info] {$message}");
        }
    }

    /**
     * @param array $payload
     * @return bool
     */
    protected function verifyPayload(array $payload)
    {
        $ts = $payload['signature']['timestamp'];
        $token = $payload['signature']['token'];
        $signature = $payload['signature']['signature'];

        if (abs(time() - $ts) > 15) {
            return false;
        }

        $apiKey = $this->options()->tmi_apiKey;

        return hash_hmac('sha256', $ts . $token, $apiKey) === $signature;
    }

    /**
     * @throws \XF\Mvc\Reply\Exception
     * @return void
     */
    protected function throwNotAcceptableRequest()
    {
        $response = $this->app()->response();
        $response->httpCode(406);

        throw $this->exception($this->message('Not Acceptable'));
    }
}
