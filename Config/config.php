<?php

return [
    'name'        => 'Extendee Form Tab',
    'description' => 'Form tab in contacts detail for Mautic',
    'author'      => 'mtcextendee.com',
    'version'     => '1.0.0',
    'services' => [
        'events' => [
            'mautic.extendee.form.tab.inject.custom.content.subscriber' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\InjectCustomContentSubscriber::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'mautic.extendee.form.tab.helper'
                ],
            ],
        ],
        'other' => [
            'mautic.extendee.form.tab.helper' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper::class,
                'arguments' => [
                    'mautic.helper.templating',
                    'mautic.form.model.form',
                    'mautic.helper.user',
                    'mautic.security',
                    'mautic.form.model.submission',
                    'mautic.helper.integration'
                ],
            ],
        ],
    ],
];
