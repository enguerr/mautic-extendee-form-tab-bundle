<?php

return [
    'name'        => 'Extendee Form Tab',
    'description' => 'Form tab in contacts detail for Mautic',
    'author'      => 'mtcextendee.com',
    'version'     => '1.0.1',
    'routes'      => [
        'public' => [
            'mautic_formtabsubmission_edit'   => [
                'path'       => '/formtab/submission/edit/{formId}/{objectId}',
                'controller' => 'MauticExtendeeFormTabBundle:Submission:edit',
            ],
            'mautic_formtabsubmission_delete' => [
                'path'       => '/formtab/submission/delete/{objectId}',
                'controller' => 'MauticExtendeeFormTabBundle:Submission:delete',
            ],
        ],
    ],
    'services'    => [
        'events' => [
            'mautic.extendee.form.tab.inject.custom.content.subscriber' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\InjectCustomContentSubscriber::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'mautic.extendee.form.tab.helper',
                ],
            ],
            'mautic.extendee.form.tab.form.subscriber'                  => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\FormSubscriber::class,
                'arguments' => [
                ],
            ],
            'mautic.extendee.form.tab.token.subscriber'                 => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\TokensSubscriber::class,
                'arguments' => [
                    'mautic.form.model.field',
                ],
            ],
            'mautic.extendee.form.tab.camapign.form.results.subscriber' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\CampaginFormResultsSubscriber::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                    'mautic.email.model.email',
                    'mautic.campaign.model.event',
                    'mautic.channel.model.queue',
                    'mautic.email.model.send_email_to_user',
                    'translator',
                    'mautic.form.model.form',
                    'mautic.form.model.field',
                    'mautic.form.model.submission',
                    'mautic.extendee.form.tab.helper',
                    'mautic.extendee.form.tab.service.save_submission',
                    'request_stack',
                    'router',
                ],
            ],
            'mautic.extendee.form.tab.campaign.date.value.subscriber'   => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\CampaignFormDateConditionSubscriber::class,
                'arguments' => [
                    'mautic.extendee.form.tab.helper',
                    'mautic.helper.core_parameters',
                ],
            ],
            'mautic.extendee.form.tab.redirect.subscriber'              => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\RedirectSubscriber::class,
                'arguments' => [
                    'mautic.model.factory',
                ],
            ],
            'mautic.form.tab.campaign.complex.condition.subscriber'     => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\CampaignComplexFormConditionSubscriber::class,
                'arguments' => [
                    'mautic.campaign.repository.event',
                    'mautic.form.tab.compare.query.builder',
                ],
            ],
            'mautic.form.tab.asset.subscriber'                          => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\EventListener\AssetSubscriber::class,
                'arguments' => [
                ],
            ],
        ],
        'forms'  => [
            'mautic.form.tab.type.submission'                         => [
                'class' => MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\SubmissionType::class,
                'alias' => 'form_tab_submission',
            ],
            'mautic.form.tab.type.modify.result'                      => [
                'class'     => MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\ModifyFormResultType::class,
                'arguments' => [
                    'mautic.extendee.form.tab.helper',
                    'request_stack',
                ],
                'alias'     => 'form_tab_modify_result',
            ],
            'mautic.form.tab.type.campaign.form.field.date.condition' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\CampaignFormDateConditionType::class,
                'arguments' => [
                    'mautic.extendee.form.tab.helper',
                ],
            ],
        ],
        'other'  => [
            'mautic.form.tab.compare.query.builder' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\Compare\CompareQueryBuilder::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.form.model.form',
                    'mautic.form.model.submission',
                    'mautic.extendee.form.tab.helper'
                ],
            ],
            'mautic.extendee.form.tab.service.save_submission' => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\Service\SaveSubmission::class,
                'arguments' => [
                    'mautic.form.model.form',
                    'mautic.helper.form.field_helper',
                    'mautic.form.validator.upload_field_validator',
                    'mautic.form.helper.form_uploader',
                    'mautic.campaign.model.campaign',
                    'event_dispatcher',
                    'translator',
                    'mautic.form.model.submission',
                    'mautic.helper.ip_lookup',
                    'doctrine.orm.entity_manager',
                    'mautic.lead.model.lead',
                    'mautic.factory',
                ],
            ],
            'mautic.extendee.form.tab.helper'                  => [
                'class'     => \MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper::class,
                'arguments' => [
                    'mautic.helper.templating',
                    'mautic.form.model.form',
                    'mautic.helper.user',
                    'mautic.security',
                    'mautic.form.model.submission',
                    'mautic.helper.integration',
                    'mautic.lead.model.lead',
                    'doctrine.orm.entity_manager',
                    'mautic.helper.core_parameters',
                    'request_stack',
                    'mautic.email.model.email',
                ],
            ],
        ],
    ],
];
