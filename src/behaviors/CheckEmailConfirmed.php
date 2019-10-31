<?php
/**
 * Identity and Access Management server providing OAuth2, multi-factor authentication and more
 *
 * @link      https://github.com/hiqdev/hiam
 * @package   hiam
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2014-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiam\behaviors;

use hiam\base\User;
use hiqdev\php\confirmator\ServiceInterface;
use Yii;
use yii\web\UserEvent;

/**
 * CheckEmailConfirmed behavior for the [\yii\web\User] component
 * Prevents login if user email is not confirmed and
 * sends confirmation email.
 */
class CheckEmailConfirmed extends \yii\base\Behavior
{
    /**
     * @var ServiceInterface
     */
    private $confirmator;

    public function __construct(ServiceInterface $confirmator, $config = [])
    {
        parent::__construct($config);
        $this->confirmator = $confirmator;
    }

    public function events()
    {
        return [
            User::EVENT_BEFORE_LOGIN => 'beforeLogin',
        ];
    }

    public function beforeLogin(UserEvent $event)
    {
        if ($event->cookieBased) {
            return;
        }

        $identity = $event->identity;
        if ($identity->isEmailConfirmed()) {
            return;
        }

        if (empty($identity->email)) {
            return;
        }

        if ($this->confirmator->mailToken($identity, 'confirm-email')) {
            Yii::$app->session->setFlash('warning',
                Yii::t('hiam', 'Please confirm your email address!') . '<br/>' .
                Yii::t('hiam', 'An email with confirmation instructions was sent to <b>{email}</b>', ['email' => $identity->email])
            );
        } else {
            Yii::$app->session->setFlash('error', Yii::t('hiam', 'Sorry, we are unable to confirm your email.'));
        }

        Yii::$app->response->redirect(Yii::$app->getHomeUrl());
        Yii::$app->end();
    }
}
