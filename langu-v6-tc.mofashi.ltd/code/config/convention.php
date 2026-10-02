<?php

// 惯例配置

use App\Enums\ConfigKey;
use App\Enums\GroupConfigKey;
use App\Enums\ImagePermission;
use App\Enums\Mail\SmtpOption;
use App\Enums\PastedAction;
use App\Enums\Scan\AliyunOption;
use App\Enums\Scan\NsfwJsOption;
use App\Enums\Scan\TencentOption;
use App\Enums\UserConfigKey;

return [
    'app' => [
        ConfigKey::AppName => '魔法师图床',
        ConfigKey::AppVersion => 'V 2.1',
        ConfigKey::SiteKeywords => '魔法师图床,图床,免费图床,图床外链',
        ConfigKey::SiteDescription => '一款简单好用的免费图床',
        ConfigKey::SiteNotice => '',
        ConfigKey::IcpNo => '',
        ConfigKey::IsEnableRegistration => 1,
        ConfigKey::IsEnableGallery => 1,
        ConfigKey::IsEnableApi => 1,
        ConfigKey::IsAllowGuestUpload => 1,
        ConfigKey::UserInitialCapacity => 512000,
        ConfigKey::IsUserNeedVerify => 0,
        ConfigKey::Mail => [
            'default' => 'smtp',
            'mailers' => [
                'smtp' => [
                    SmtpOption::Transport => 'smtp',
                    SmtpOption::Host => 'smtp.mailgun.org',
                    SmtpOption::Port => 587,
                    SmtpOption::Encryption => 'tls',
                    SmtpOption::Username => '',
                    SmtpOption::Password => '',
                    SmtpOption::Timeout => null,
                ]
            ],
        ],
    ],
    'group' => [
        GroupConfigKey::MaximumFileSize => 5120,
        GroupConfigKey::ConcurrentUploadNum => 3,
        GroupConfigKey::IsEnableScan => 0,
        GroupConfigKey::ScannedAction => 'mark', // in mark or delete
        GroupConfigKey::ScanConfigs => [
            'driver' => 'tencent',
            'drivers' => [
                'tencent' => [
                    TencentOption::Endpoint => 'ims.tencentcloudapi.com',
                    TencentOption::SecretId => '',
                    TencentOption::SecretKey => '',
                    TencentOption::Region => '',
                    TencentOption::BizType => ''
                ],
                'aliyun' => [
                    AliyunOption::AccessKeyId => '',
                    AliyunOption::AccessKeySecret => '',
                    AliyunOption::RegionId => '',
                    AliyunOption::Scenes => ['porn'],
                    AliyunOption::BizType => '',
                ],
                'nsfwjs' => [
                    NsfwJsOption::ApiUrl => '',
                    NsfwJsOption::AttrName => 'image',
                    NsfwJsOption::Threshold => 60,
                ]
            ],
        ],
        GroupConfigKey::LimitPerMinute => 20,
        GroupConfigKey::LimitPerHour => 100,
        GroupConfigKey::LimitPerDay => 300,
        GroupConfigKey::LimitPerWeek => 600,
        GroupConfigKey::LimitPerMonth => 999,
        GroupConfigKey::AcceptedFileSuffixes => ['jpeg', 'jpg', 'png', 'gif', 'tif', 'bmp', 'ico', 'psd', 'webp'],
        GroupConfigKey::ImageSaveFormat => '',
        GroupConfigKey::ImageSaveQuality => 75,
        GroupConfigKey::PathNamingRule => '{Y}/{m}/{d}',
        GroupConfigKey::FileNamingRule => '{uniqid}',
    ],
    'user' => [
        UserConfigKey::DefaultAlbum => 0,
        UserConfigKey::DefaultStrategy => 0,
        UserConfigKey::DefaultPermission => ImagePermission::Private,
        UserConfigKey::PastedAction => PastedAction::Waiting,
        UserConfigKey::IsAutoClearPreview => false,
    ]
];
