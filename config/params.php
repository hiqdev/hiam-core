<?php
/**
 * Identity and Access Management server providing OAuth2, multi-factor authentication and more
 *
 * @link      https://github.com/hiqdev/hiam
 * @package   hiam
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2014-2018, HiQDev (http://hiqdev.com/)
 */

return [
    'hiam.authorizedClients' => array_filter([
        'demo' => $_ENV['ENV'] !== 'PROD' ? 'pass' : null,
    ]),

    'hiapi.var_dir'     => dirname(__DIR__, 4) . '/../HIAPI_VAR',

    'logoUrl'           => '/site/back',

    'poweredBy.name'    => 'HIAM',
    'poweredBy.url'     => 'https://github.com/hiqdev/hiam',

    'organization.name' => '',

    'supportEmail'      => '',

    'db.host'           => '',
    'db.port'           => '',
    'db.name'           => '',
    'db.user'           => '',
    'db.password'       => '',

    'user.seller'                   => '',
    'user.loginDuration'            => 3600 * 24 * 31,
    'user.passwordResetTokenExpire' => 3600,
    'user.enableAutoLogin'          => true,
    'user.disableSignup'            => false,
    'user.disableRestorePassword'   => false,
    'user.htmlEmails'               => false,
    'user.checkEmailConfirmed'      => true,

    'user.authKeySecret'        => '',
    'user.authKeyCipher'        => 'aes-128-gcm',

    'swiftmailer.smtp.host'     => null,
    'swiftmailer.smtp.port'     => 25,
];
