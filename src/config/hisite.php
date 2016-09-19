<?php

/*
 * Identity and Access Management server providing OAuth2, RBAC and logging
 *
 * @link      https://github.com/hiqdev/hiam-core
 * @package   hiam-core
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2014-2016, HiQDev (http://hiqdev.com/)
 */

$authClients = require __DIR__ . '/authClients.php';

return [
    'id' => 'hiam',
    'name' => 'HIAM',
    'layout' => 'mini',
    'controllerNamespace' => 'hiam\controllers',
    'components' => [
        'db' => [
            'class'     => \yii\db\Connection::class,
            'charset'   => 'utf8',
            'dsn'       => 'pgsql:dbname=' . (empty($params['db_name']) ? 'hiam' : $params['db_name']),
            'username'  => empty($params['db_user']) ? 'hiam' : $params['db_user'],
            'password'  => empty($params['db_password']) ? '*' : $params['db_password'],
        ],
        'user' => [
            'class'           => \yii\web\User::class,
            'identityClass'   => \hiam\models\User::class,
            'enableAutoLogin' => true,
        ],
        'mailer' => [
            'class' => \yii\swiftmailer\Mailer::class,
            'viewPath' => '@hiam/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'authManager' => [
            'class'             => \hiam\rbac\HiDbManager::class,
            'itemTable'         => '{{%rbac_item}}',
            'itemChildTable'    => '{{%rbac_item_child}}',
            'assignmentTable'   => '{{%rbac_assignment}}',
            'ruleTable'         => '{{%rbac_rule}}',
        ],
        'authClientCollection' => [
            'class' => \hiam\authclient\Collection::class,
            'clients' => $authClients,
        ],
        'themeManager' => [
            'assets' => [
                \hiam\Asset::class,
                \hiqdev\assets\icheck\iCheckAsset::class,
            ],
        ],
    ],
    'modules' => [
        'oauth2' => [
            'class' => \filsh\yii2\oauth2server\Module::class,
            'options' => [
                'enforce_state'     => true,
                'access_lifetime'   => 3600 * 24,
            ],
            'storageMap' => [
                'user_credentials'  => \hiam\models\User::class,
            ],
            'grantTypes' => [
///             'client_credentials' => [
///                 'class' => \OAuth2\GrantType\ClientCredentials::class,
///                 'allow_public_clients' => false
///             ],
                'authorization_code' => [
                    'class' => \OAuth2\GrantType\AuthorizationCode::class,
                ],
                'user_credentials' => [
                    'class' => \OAuth2\GrantType\UserCredentials::class,
                ],
                'refresh_token' => [
                    'class' => \OAuth2\GrantType\RefreshToken::class,
                    'always_issue_new_refresh_token' => true,
                ],
            ],
        ],
    ],
];
