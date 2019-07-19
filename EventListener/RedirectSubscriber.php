<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\PageBundle\Event\RedirectEvent;
use Mautic\PageBundle\PageEvents;

class RedirectSubscriber extends CommonSubscriber
{
    /**
     * @var ModelFactory
     */
    private $modelFactory;

    /**
     * RedirectSubscriber constructor.
     *
     * @param ModelFactory $modelFactory
     */
    public function __construct(ModelFactory $modelFactory)
    {
        $this->modelFactory = $modelFactory;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PageEvents::ON_REDIRECT => ['onRedirect', 0],
        ];
    }

    /**
     * @param RedirectEvent $redirectEvent
     */
    public function onRedirect(RedirectEvent $redirectEvent)
    {
        // stop if no token in url
        if (strpos($redirectEvent->getUrl(), '{') === false) {
            return;
        }

        $clickthrough = $redirectEvent->getClickthrough();
        if (isset($clickthrough['channel']) && is_array($clickthrough['channel']) && isset($clickthrough['stat'])) {
            /** @var EmailModel $model */
            $channel = array_keys($clickthrough['channel']);
            $channel = end($channel);
            $model   = $this->modelFactory->getModel($channel);
            if ($model && method_exists($model, 'getStatRepository')) {
                $statRepo = $model->getStatRepository();
                if ($statRepo) {
                    if ($stat = $statRepo->findOneBy(['trackingHash' => $clickthrough['stat']])) {
                        if (method_exists($stat, 'getTokens')) {
                            $tokens = $stat->getTokens();
                            $redirectEvent->setUrl(str_replace(array_keys($tokens), $tokens, $redirectEvent->getUrl()));
                        }
                    }
                }
            }
        }
    }
}
