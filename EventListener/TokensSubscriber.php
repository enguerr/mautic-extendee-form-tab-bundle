<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Model\FieldModel;

/**
 * Class TokensSubscriber.
 */
class TokensSubscriber extends CommonSubscriber
{

    /**
     * @var FieldModel
     */
    private $fieldModel;

    /**
     * TokensSubscriber constructor.
     *
     * @param FieldModel $fieldModel
     */
    public function __construct(FieldModel $fieldModel)
    {

        $this->fieldModel = $fieldModel;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EmailEvents::EMAIL_ON_BUILD   => ['onBuildBuilder', 0],

        ];
    }

    /**
     * Add field tokens to email
     *
     * @param EmailBuilderEvent $event
     */
    public function onBuildBuilder(EmailBuilderEvent $event)
    {
        // register tokens
        $tokens = [];

        $fields = $this->fieldModel->getEntities();
        /** @var Field $field */
        foreach ($fields as $field) {
            $tokens['{formfield='.$field->getAlias().'}'] = $field->getLabel();
        }
        if ($event->tokensRequested(array_keys($tokens))) {
            $event->addTokens(
                $event->filterTokens($tokens)
            );
        }
    }


}
