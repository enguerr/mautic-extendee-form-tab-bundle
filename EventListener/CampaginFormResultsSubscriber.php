<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CampaignBundle\Model\EventModel;
use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\ParamsLoaderHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailOpenEvent;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\EmailBundle\Model\SendEmailToUser;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Model\FieldModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\EmailSendResultType;
use MauticPlugin\MauticExtendeeFormTabBundle\Form\Type\ModifyFormResultType;
use MauticPlugin\MauticExtendeeFormTabBundle\FormTabEvents;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Service\SaveSubmission;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;


/**
 * Class CampaginFormResultsSubscriber.
 */
class CampaginFormResultsSubscriber implements EventSubscriberInterface
{
    CONST ALLOWED_FORM_TAB_CONDITIONS = ['form.tab.date.condition', 'form.field_value', CampaignComplexFormConditionSubscriber::EVENT_NAME];

    CONST ALLOWED_FORM_TAB_DECISIONS  = ['email.open'];

    /** @var  array */
    private static $parameters;

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var EmailModel
     */
    protected $emailModel;

    /**
     * @var EmailModel
     */
    protected $messageQueueModel;

    /**
     * @var EventModel
     */
    protected $campaignEventModel;

    /**
     * @var SendEmailToUser
     */
    private $sendEmailToUser;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var FormModel
     */
    private $formModel;

    /**
     * @var FieldModel
     */
    private $fieldModel;

    /**
     * @var SubmissionModel
     */
    private $submissionModel;

    /**
     * @var FormTabHelper
     */
    private $formTabHelper;

    /**
     * @var SaveSubmission
     */
    private $saveSubmission;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Router
     */
    private $router;

    /**
     * @param LeadModel           $leadModel
     * @param EmailModel          $emailModel
     * @param EventModel          $eventModel
     * @param MessageQueueModel   $messageQueueModel
     * @param SendEmailToUser     $sendEmailToUser
     * @param TranslatorInterface $translator
     * @param FormModel           $formModel
     * @param FieldModel          $fieldModel
     * @param SubmissionModel     $submissionModel
     * @param FormTabHelper       $formTabHelper
     * @param SaveSubmission      $saveSubmission
     * @param RequestStack        $requestStack
     * @param Router              $router
     */
    public function __construct(
        LeadModel $leadModel,
        EmailModel $emailModel,
        EventModel $eventModel,
        MessageQueueModel $messageQueueModel,
        SendEmailToUser $sendEmailToUser,
        TranslatorInterface $translator,
        FormModel $formModel,
        FieldModel $fieldModel,
        SubmissionModel $submissionModel,
        FormTabHelper $formTabHelper,
        SaveSubmission $saveSubmission,
        RequestStack $requestStack,
        Router $router
    ) {
        $this->leadModel          = $leadModel;
        $this->emailModel         = $emailModel;
        $this->campaignEventModel = $eventModel;
        $this->messageQueueModel  = $messageQueueModel;
        $this->sendEmailToUser    = $sendEmailToUser;
        $this->translator         = $translator;
        $this->formModel          = $formModel;
        $this->fieldModel         = $fieldModel;
        $this->submissionModel    = $submissionModel;
        $this->formTabHelper      = $formTabHelper;
        $this->saveSubmission     = $saveSubmission;
        $this->requestStack       = $requestStack;
        $this->router             = $router;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD         => ['onCampaignBuild', -1],
            FormTabEvents::ON_CAMPAIGN_BATCH_ACTION   => [
                ['onCampaignTriggerActionSendEmailToContact', 1],
                ['onCampaignTriggerActionModifyFormResults', 0],
            ],
            EmailEvents::ON_CAMPAIGN_TRIGGER_DECISION => ['onCampaignTriggerDecision', -1],
            EmailEvents::EMAIL_ON_OPEN                => ['onEmailOpen', 1],
        ];
    }

    /**
     * Trigger campaign event for opening of an email.
     *
     * @param EmailOpenEvent $event
     */
    public function onEmailOpen(EmailOpenEvent $event)
    {
        $email = $event->getEmail();
        if ($email !== null && $event->getStat()->getSource() === 'form.result') {
            $this->campaignEventModel->triggerEvent('email.open', $event, 'email', $email->getId());
        }
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerDecision(CampaignExecutionEvent $event)
    {
        /** @var Email $eventDetails */
        $eventDetails = $event->getEventDetails();
        /** @var EmailOpenEvent $emailOpenEvent */
        $emailOpenEvent = $eventDetails;
        $eventParent    = $event->getEvent()['parent'];
        if ($event->checkContext(
                'email.open'
            ) && !empty($eventParent) && $eventParent['type'] === 'email.send.form.results') {
            if (method_exists($emailOpenEvent, 'getStat')) {
                $event->setChannel('form.result', $emailOpenEvent->getStat()->getId());
                $event->getLogEntry()->setChannel('form.result');
                $event->getLogEntry()->setChannelId($emailOpenEvent->getStat()->getSourceId());
                $event->getLogEntry()->setMetadata(['submissionId' => $emailOpenEvent->getStat()->getId()]);

                return $event->setResult(
                    $eventDetails->getEmail()->getId() === (int) $eventParent['properties']['email']
                );
            }
        }
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        $event->addAction(
            'email.send.form.results',
            [
                'label'                  => 'mautic.extendee.form.tab.campaign.event.send',
                'description'            => 'mautic.extendee.form.tab.campaign.event.send.desc',
                'batchEventName'         => FormTabEvents::ON_CAMPAIGN_BATCH_ACTION,
                'formType'               => EmailSendResultType::class,
                'formTheme'              => 'MauticEmailBundle:FormTheme\EmailSendList',
                'channel'                => 'email',
                'channelIdField'         => 'email',
                'connectionRestrictions' => [
                    'target' => [
                        'decision' => self::ALLOWED_FORM_TAB_DECISIONS,
                    ],
                    'anchor' => [
                        'condition.inaction',
                    ],
                    'source' => [
                        'condition' => [
                            'form.field_value',
                            'form.tab.date.condition',
                            CampaignComplexFormConditionSubscriber::EVENT_NAME
                        ],
                    ],
                ],
            ]
        );

        $event->addAction(
            'modify.form.result',
            [
                'label'                  => 'mautic.extendee.form.tab.campaign.event.modify.results',
                'description'            => 'mautic.extendee.form.tab.campaign.event.modify.results.desc',
                'batchEventName'         => FormTabEvents::ON_CAMPAIGN_BATCH_ACTION,
                'formType'               => ModifyFormResultType::class,
                'formTheme'              => 'MauticExtendeeFormTabBundle:FormTheme\ModifyFormResultType',
                'connectionRestrictions' => [
                    'anchor' => [
                        'condition.inaction',
                    ],
                    'source' => [
                        'condition' => [
                            'form.field_value',
                            'form.tab.date.condition',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * @param Event $event
     *
     * @return array
     */
    private function getEventParents(\Mautic\CampaignBundle\Entity\Event $event)
    {
        $eventParent = $event->getParent();
        $events      = [];
        while (is_object($eventParent) && (in_array(
                    $eventParent->getType(),
                    self::ALLOWED_FORM_TAB_CONDITIONS
                ) || in_array(
                    $eventParent->getType(),
                    self::ALLOWED_FORM_TAB_DECISIONS
                ))) {

            $events[]    = $eventParent;
            $eventParent = $eventParent->getParent();
            if (!$eventParent) {
                break;
            }
        }

        return $events;
    }

    /**
     * Triggers the action which sends email to contacts.
     *
     * @param PendingEvent $event
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function onCampaignTriggerActionModifyFormResults(PendingEvent $event)
    {
        if (!$event->checkContext('modify.form.result')) {
            return;
        }
        $config = $event->getEvent()->getProperties();
        $form   = $this->formModel->getRepository()->findOneById($config['form']);

        if (!$form || !$form->getId()) {
            $event->failAll('Form not found.');

            return;
        }

        $event->setChannel('form', $config['form']);

        /** @var Event $eventParent */
        $eventParents = $this->getEventParents($event->getEvent());
        // Return failed If parent form ID  not equal to modified form reuslts
        if (!$this->formTabHelper->continueAfterDecision($eventParents)) {
            $eventParentsIds = $this->formTabHelper->getRelatedFormIdsFromEvents($eventParents, $config['form']);
            if (empty($eventParentsIds)) {
                $event->failAll(
                    'Parent form ids are not same like form what you want to modify in form #'.$config['form']
                );

                return;
            }
        }
        // Determine if this email is transactional/marketing
        $pending    = $event->getPending();
        $contacts   = $event->getContacts();
        $contactIds = $event->getContactIds();
        $server     = $_SERVER;
        /**
         * @var int
         * @var Lead $contact
         */
        foreach ($contacts as $logId => $contact) {

            $formResults = $this->formTabHelper->getFormWithResult($form, $contact->getId(), true);
            if (empty($formResults['results']['count'])) {
                unset($contactIds[$contact->getId()]);
                $event->fail($pending->get($logId), 'No form results for contact #'.$contact->getId());
                continue;
            }
            $resultsIds = $this->formTabHelper->formResultsFromFromEvents($eventParents, $contact);
            $errors     = [];
            foreach ($formResults['results']['results'] as $results) {
                if (!in_array($results['id'], $resultsIds)) {
                    continue;
                }
                $fields               = $results['results'];
                $post                 = [];
                $post['submissionId'] = $results['id'];
                foreach ($fields as $field) {
                    if (!empty($config['content'][$field['alias']])) {
                        $post[$field['alias']] = $config['content'][$field['alias']];
                    } else {
                        $post[$field['alias']] = $field['value'];
                    }
                }
                $result = $this->saveSubmission->saveSubmission(
                    $post,
                    $server,
                    $form,
                    null,
                    true,
                    $contact

                );
                if (!$result instanceof Submission && !empty($result['errors'])) {
                    $errors[] = $result['errors'];
                }
            }
            if (!empty($errors)) {
                $event->fail($pending->get($logId), implode(', ', $errors));
            } else {
                $event->pass($pending->get($logId));
            }
        }
    }

    /**
     * Triggers the action which sends email to contacts.
     *
     * @param PendingEvent $event
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function onCampaignTriggerActionSendEmailToContact(PendingEvent $event)
    {
        if (!$event->checkContext('email.send.form.results')) {
            return;
        }

        $config  = $event->getEvent()->getProperties();
        $formId  = $config['form'];
        $emailId = (int) $config['email'];
        $email   = $this->emailModel->getEntity($emailId);

        if (!$email || !$email->isPublished()) {
            $event->failAll('Email not found or published');

            return;
        }

        /** @var Event $eventParent */
        $eventParents = $this->getEventParents($event->getEvent());
        // Return failed If parent form ID  not equal to modified form reuslts
        $eventParentsIds = $this->formTabHelper->getRelatedFormIdsFromEvents($eventParents, $config['form']);
        if (empty($eventParentsIds)) {
           /* $event->failAll('Parent form ids are not same like form what you want to modify in form #'.$config['form']);

            return;*/
        }

        $form = $this->formModel->getRepository()->findOneById($formId);

        if (!$form || !$form->getId()) {
            $event->failAll('Parent form not found.');

            return;
        }

        $event->setChannel('email', $emailId);

        $options = [
            'source'        => ['campaign.event', $event->getEvent()->getId()],
            'return_errors' => true,
            'dnc_as_error'  => true,
        ];

        // Determine if this email is transactional/marketing
        $pending    = $event->getPending();
        $contacts   = $event->getContacts();
        $contactIds = $event->getContactIds();
        /**
         * @var int
         * @var Lead $contact
         */
        $emailContent           = $email->getCustomHtml();
        $dynamicContentAsArrays = $email->getDynamicContent();
        $emailContentAll = $emailContent.''.implode('', array_column($dynamicContentAsArrays, 'content'));

        //$email->getDynamicContent();
        foreach ($contacts as $logId => $contact) {
            $leadCredentials = $contact->getProfileFields();

            // Set owner_id to support the "Owner is mailer" feature
            if ($contact->getOwner()) {
                $leadCredentials['owner_id'] = $contact->getOwner()->getId();
            }
            if (empty($leadCredentials['email'])) {
                // Pass with a note to the UI because no use retrying
                $event->passWithError(
                    $pending->get($logId),
                    $this->translator->trans(
                        'mautic.email.contact_has_no_email',
                        ['%contact%' => $contact->getPrimaryIdentifier()]
                    )
                );
                unset($contactIds[$contact->getId()]);
                continue;
            }

            $formResults = $this->formTabHelper->getFormWithResult($form, $contact->getId(), true);
            if (empty($formResults['results']['count'])) {
                unset($contactIds[$contact->getId()]);;
                continue;
            }

            $resultsIds = $this->formTabHelper->formResultsFromFromEvents($eventParents, $contact);
            $reason     = [];
            foreach ($formResults['results']['results'] as $results) {
                // check
                if (!in_array($results['id'], $resultsIds)) {
                    continue;
                }
                $options['tokens'] = $this->findTokens($emailContentAll, $results);
               // $newEmailContent = $this->replaceTokensFromContent($emailContent, $results);
              //  $newEmailContent = $emailContent;

                // replace dynamic content tokens
             /*   $dynamicContentAsArraysNew = $dynamicContentAsArrays;
                foreach ($dynamicContentAsArraysNew as &$dynamicContentAsArray) {
                    $dynamicContentAsArray['content'] = $this->replaceTokensFromContent(
                        $dynamicContentAsArray['content'],
                        $results
                    );
                    if (!empty($dynamicContentAsArray['filters'])) {
                        foreach ($dynamicContentAsArray['filters'] as &$dynamicContentFilter) {
                            $dynamicContentFilter['content'] = $this->replaceTokensFromContent(
                                $dynamicContentFilter['content'],
                                $results
                            );
                        }
                    }
                }
                $email->setDynamicContent($dynamicContentAsArraysNew);


                // replace all form field tokens
                $email->setCustomHtml($newEmailContent);*/

                $options['channel'] = ['form.result', $results['id']];
                $result             = $this->emailModel->sendEmail($email, $leadCredentials, $options);
                if (is_array($result)) {
                    $reason[] = implode('<br />', $result);
                } elseif (true !== $result) {
                    $reason[] = $result;
                }
            }
            if (!empty($reason)) {
                $event->fail($pending->get($logId), implode('<br />', $reason));
            } else {
                $event->pass($pending->get($logId));
            }
        }

        $email->setCustomHtml($emailContent);

    }


    /**
     * @param $content
     * @param $results
     *
     * @return string
     */
    public function replaceTokensFromContent($content, $results)
    {
        $tokens = $this->findTokens($content, $results);

        return str_replace(array_keys($tokens), $tokens, $content);
    }

    /**
     * @param array $content
     * @param array $results
     *
     * @return array
     */
    private function findTokens($content, $results)
    {

        // Search for bracket or bracket encoded
        // @deprecated BC support for leadfield
        $tokenRegex = [
            '/({|%7B)formfield=(.*?)(}|%7D)/',
        ];
        $tokenList  = [];

        foreach ($tokenRegex as $regex) {
            $foundMatches = preg_match_all($regex, $content, $matches);
            if ($foundMatches) {
                foreach ($matches[2] as $key => $match) {
                    if (false !== strpos($match, '%7C')) {
                        $match = urldecode($match);
                    }
                    $token = $matches[0][$key];

                    if (isset($tokenList[$token])) {
                        continue;
                    }
                    $value = $this->getTokenValue($results, $match);;
                    $tokenList[$token] = $value;
                }
            }
        }

        return $tokenList;
    }

    /**
     * @param $results
     * @param $alias
     *
     * @return string
     */
    private function getTokenValue($results, $alias)
    {
        $modifier = '';
        if (count(explode('|', $alias)) === 2) {
            list($alias, $modifier) = explode('|', $alias);
        }
        $value       = '';
        $formResults = $results['results'];
        if (isset($formResults[$alias])) {
            if ('file' === $formResults[$alias]['type'] && !empty($formResults[$alias]['value'])) {
                $value = $this->router->generate(
                    'mautic_form_file_download',
                    [
                        'submissionId' => $results['id'],
                        'field'        => $alias,
                    ],
                    true
                );
            } else {
                $value = $formResults[$alias]['value'];
            }
        }

        switch ($modifier) {
            case 'true':
                $value = urlencode($value);
                break;
            case 'datetime':
            case 'date':
            case 'time':
                $dt   = new DateTimeHelper($value);
                $date = $dt->getDateTime()->format(
                    self::getParameter('date_format_dateonly')
                );
                $time = $dt->getDateTime()->format(
                    self::getParameter('date_format_timeonly')
                );
                switch ($modifier) {
                    case 'datetime':
                        $value = $date.' '.$time;
                        break;
                    case 'date':
                        $value = $date;
                        break;
                    case 'time':
                        $value = $time;
                        break;
                }
                break;
        }
        if (in_array($modifier, ['true', 'date', 'time', 'datetime'])) {
            return $value;
        } else {
            return $value ?: $modifier;
        }
    }

    /**
     * @param string $parameter
     *
     * @return mixed
     */
    private static function getParameter($parameter)
    {
        if (null === self::$parameters) {
            self::$parameters = (new ParamsLoaderHelper())->getParameters();
        }

        return self::$parameters[$parameter];
    }
}
