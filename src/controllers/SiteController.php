<?php
/**
 * Identity and Access Management server providing OAuth2, multi-factor authentication and more
 *
 * @link      https://github.com/hiqdev/hiam
 * @package   hiam
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2014-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiam\controllers;

use hiam\actions\ConfirmEmail;
use hiam\base\User;
use hiam\forms\ChangeEmailForm;
use hiam\forms\ConfirmPasswordForm;
use hiam\forms\LoginForm;
use hiam\forms\ResetPasswordForm;
use hiam\forms\RestorePasswordForm;
use hiam\forms\SignupForm;
use hiam\models\Identity;
use hiqdev\php\confirmator\ServiceInterface;
use hiqdev\yii2\mfa\filters\ValidateAuthenticationFilter;
use hisite\actions\RedirectAction;
use hisite\actions\RenderAction;
use hisite\actions\ValidateAction;
use Yii;
use yii\authclient\AuthAction;
use yii\authclient\ClientInterface;
use yii\filters\AccessControl;
use hiam\forms\ChangePasswordForm;

/**
 * Site controller.
 *
 * @property User $user
 */
class SiteController extends \hisite\controllers\SiteController
{
    public $defaultAction = 'lockscreen';

    /**
     * @var ServiceInterface
     */
    private $confirmator;

    public function __construct($id, $module, ServiceInterface $confirmator, $config = [])
    {
        parent::__construct($id, $module, $config = []);

        $this->confirmator = $confirmator;
    }

    public function behaviors()
    {
        $actions = [
            'signup', 'login', 'remote-proceed',
            'confirm-password', 'restore-password', 'reset-password',
            'terms', 'privacy-policy',
        ];

        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'only' => array_merge($actions, ['lockscreen']),
                'denyCallback' => function () {
                    return $this->redirect([$this->user->getIsGuest() ? 'login' : 'lockscreen']);
                },
                'rules' => [
                    // ? - guest
                    [
                        'actions' => $actions,
                        'roles' => ['?'],
                        'allow' => true,
                    ],
                    // @ - authenticated
                    [
                        'actions' => ['lockscreen', 'privacy-policy', 'terms'],
                        'roles' => ['@'],
                        'allow' => true,
                    ],
                ],
            ],
            'validateAuthentication' => [
                'class' => ValidateAuthenticationFilter::class,
                'only' => ['lockscreen'],
            ],
        ]);
    }

    public function actions()
    {
        return array_merge(parent::actions(), [
            'auth' => [
                'class' => AuthAction::class,
                'successCallback' => function (ClientInterface $client) {
                    $user = $this->user->findIdentityByAuthClient($client);
                    if ($user) {
                        $this->user->login($user);
                    }
                },
            ],
            'lockscreen' => [
                'class' => RenderAction::class,
            ],
            'back' => [
                'class' => RedirectAction::class,
                'url' => Yii::$app->params['site_url'],
            ],
            'terms' => [
                'class' => RedirectAction::class,
                'url' => Yii::$app->params['terms_url'],
            ],
            'privacy-policy' => [
                'class' => RedirectAction::class,
                'url' => Yii::$app->params['legals.privacyPolicyUrl'],
            ],
            'signup-validate' => [
                'class' => ValidateAction::class,
                'form' => SignupForm::class,
            ],
            'confirm-email' => [
                'class' => ConfirmEmail::class,
            ],
        ]);
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return Yii::$app->user;
    }

    public function actionLogin($username = null)
    {
        $client = Yii::$app->authClientCollection->getActiveClient();
        if ($client) {
            return $this->redirect(['remote-proceed']);
        }

        return $this->doLogin(new LoginForm(), 'login', $username);
    }

    protected function doLogin(LoginForm $model, $view, $username = null)
    {
        $model->username = $username;
        /** @noinspection NotOptimalIfConditionsInspection */
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $identity = $this->user->findIdentity($model->username, $model->password);
            if ($identity && $this->login($identity, $model->remember_me)) {
                return $this->goBack();
            }

            $model->addError('password', Yii::t('hiam', 'Incorrect username or password.'));
            $model->password = null;
        }

        return $this->render($view, compact('model'));
    }

    /**
     * Logs user in and preserves return URL.
     */
    private function login(Identity $identity, $sessionDuration = 0): bool
    {
        $returnUrl = $this->user->getReturnUrl();

        $result = $this->user->login($identity, $sessionDuration ? null : 0);
        if ($result && $returnUrl !== null) {
            $this->user->setReturnUrl($returnUrl);
        }

        return $result;
    }

    public function actionConfirmPassword()
    {
        $client = Yii::$app->authClientCollection->getActiveClient();
        if (!$client) {
            return $this->redirect(['login']);
        }

        try {
            $email = $client->getUserAttributes()['email'];
            $user = $this->user->findIdentityByEmail($email);
        } catch (\Exception $e) {
            return $this->redirect(['logout']);
        }

        $res = $this->doLogin(new ConfirmPasswordForm(), 'confirmPassword', $user ? $user->email : null);
        $user = $this->user->getIdentity();
        if ($user) {
            $this->user->setRemoteUser($client, $user);
        }

        return $res;
    }

    public function actionRemoteProceed()
    {
        $client = Yii::$app->authClientCollection->getActiveClient();
        if (!$client) {
            return $this->redirect(['login']);
        }

        try {
            $email = $client->getUserAttributes()['email'];
            $user = $this->user->findIdentityByEmail($email);
        } catch (\Exception $e) {
            return $this->redirect(['logout']);
        }

        if ($user) {
            return $this->redirect(['confirm-password']);
        }

        return $this->redirect(['signup']);
    }

    public function actionSignup()
    {
        if ($this->user->disableSignup) {
            Yii::$app->session->setFlash('error', Yii::t('hiam', 'Sorry, signup is disabled.'));

            return $this->redirect(['login']);
        }

        $client = Yii::$app->authClientCollection->getActiveClient();

        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($user = $this->user->signup($model)) {
                if ($client) {
                    $this->user->setRemoteUser($client, $user);
                }
                if ($this->confirmator->mailToken($user, 'confirm-email')) {
                    Yii::$app->session->setFlash('warning',
                        Yii::t('hiam', 'Please confirm your email address!') . '<br/>' .
                        Yii::t('hiam',
                            'An email with confirmation instructions was sent to <b>{email}</b>',
                            ['email' => $user->email]
                        )
                    );
                } else {
                    Yii::error('Failed to send email confirmation letter', __METHOD__);
                }
                Yii::$app->session->setFlash('success', Yii::t('hiam', 'Your account has been successfully created.'));

                return $this->goBack();
            }
        } else {
            if ($client) {
                try {
                    $data = $client->getUserAttributes();
                } catch (\Exception $e) {
                    return $this->redirect(['logout']);
                }
                $model->load([$model->formName() => $data]);
            }
        }

        return $this->render('signup', compact('model'));
    }

    public function actionRestorePassword($username = null)
    {
        if ($this->user->disableRestorePassword) {
            Yii::$app->session->setFlash('error', Yii::t('hiam', 'Sorry, password restore is disabled.'));

            return $this->redirect(['login']);
        }

        $model = new RestorePasswordForm();
        $model->username = $username;
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $user = $this->user->findIdentityByUsername($model->username);
            if ($this->confirmator->mailToken($user, 'restore-password')) {
                Yii::$app->session->setFlash('success',
                    Yii::t('hiam', 'Check your email {maskedMail} for further instructions.', [
                        'maskedMail' => $model->maskEmail($user->email),
                    ])
                );

                return $this->goHome();
            } else {
                Yii::$app->session->setFlash('error', Yii::t('hiam', 'Sorry, we are unable to reset password the provided username or email. Try to contact support team.'));
            }
        }

        return $this->render('restorePassword', compact('model'));
    }

    public function actionResetPassword($token = null)
    {
        $model = new ResetPasswordForm();
        $reset = $this->resetPassword($model, $token);

        if (isset($reset)) {
            if ($reset) {
                Yii::$app->session->setFlash('success', Yii::t('hiam', 'New password was saved.'));
            } else {
                Yii::$app->session->setFlash('error', Yii::t('hiam', 'Failed reset password. Please start over.'));
            }

            return $this->goHome();
        }

        return $this->render('resetPassword', compact('model', 'token'));
    }

    public function actionChangePassword()
    {
        $model = new ChangePasswordForm();
        $model->login = Yii::$app->user->identity->username;

        return $this->changeRoutine($model);
    }

    public function actionChangeEmail()
    {
        $model = new ChangeEmailForm();
        $identity = Yii::$app->user->identity;
        $model->seller_id = $identity->seller_id;
        $model->login = $identity->username;

        return $this->changeRoutine($model);
    }

    public function resetPassword($model, $token)
    {
        $token = $this->confirmator->findToken($token);
        if (!$token || !$token->check(['action' => 'restore-password'])) {
            return false;
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $user = $this->user->findIdentity($token->get('username'));
            if (!$user) {
                return false;
            }
            $user->password = $model->password;
            $res = $user->save();
            if ($res) {
                $token->remove();
            }

            return $res;
        }

        return null;
    }

    /**
     * @param ChangePasswordForm|ChangeEmailForm $model
     */
    private function changeRoutine($model)
    {
        $map = [
            ChangePasswordForm::class => [
                'method' => 'changePassword',
                'view' => 'change-password',
                'label' => 'Password',
            ],
            ChangeEmailForm::class => [
                'method' => 'changeEmail',
                'view' => 'change-email',
                'label' => 'Email',
            ],
        ];
        $sender = $map[get_class($model)];
        $request = Yii::$app->request;

        if ($request->isPost) {
            if ($model->load($request->post()) && $model->validate() && $this->user->{$sender['method']}($model)) {
                Yii::$app->session->setFlash('success', Yii::t('hiam', "{$sender['label']} has been successfully changed"));

                return $this->goBack();
            } else {
                $errors = implode("; \n", $model->getFirstErrors());
                if (!$errors) {
                    $errors = Yii::t('hiam', "{$sender['label']} has not been changed");
                }
                Yii::$app->session->setFlash('error', $errors);
            }
        }

        return $this->render($sender['view'], ['model' => $model]);
    }
}
