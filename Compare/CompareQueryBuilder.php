<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Compare;


use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\CoreBundle\Helper\ParamsLoaderHelper;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEventDTO;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEvents;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CompareEvent;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabDateHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use Monolog\Logger;

class CompareQueryBuilder
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var FormModel
     */
    private $formModel;

    /**
     * @var SubmissionModel
     */
    private $submissionModel;

    /**
     * @var CampaignEventDTO
     */
    private $campaignEvent;

    /**
     * @var QueryBuilder[]
     */
    private $subQueries;

    /**
     * @var array
     */
    private $subQueriesConditions;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $tableAlias;

    /**
     * @var Form
     */
    private $form;

    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var FormTabDateHelper
     */
    private $formTabDateHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * CompareQueryBuilder constructor.
     *
     * @param EntityManager     $entityManager
     * @param FormModel         $formModel
     * @param SubmissionModel   $submissionModel
     * @param LeadModel         $leadModel
     * @param FormTabDateHelper $formTabDateHelper
     * @param Logger            $logger
     */
    public function __construct(
        EntityManager $entityManager,
        FormModel $formModel,
        SubmissionModel $submissionModel,
        LeadModel $leadModel,
        FormTabDateHelper $formTabDateHelper,
        Logger $logger
    ) {
        $this->entityManager   = $entityManager;
        $this->formModel       = $formModel;
        $this->submissionModel = $submissionModel;
        $this->leadModel = $leadModel;
        $this->formTabDateHelper = $formTabDateHelper;
        $this->logger = $logger;
    }

    public function compareValue(CampaignEvents $campaignEvents)
    {
        $this->subQueries = [];
        $this->subQueriesConditions = [];
        //use DBAL to get entity fields
        $q = $this->entityManager->getConnection()->createQueryBuilder();
        $q->select('s.id')
            ->from(MAUTIC_TABLE_PREFIX.'form_submissions', 's')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('s.lead_id', ':leadId')
                )
            )
            ->setParameter('leadId', $campaignEvents->first()->getContact()->getId());

        foreach ($campaignEvents->getCampaignEvents() as $campaignEvent) {
            $this->campaignEvent = $campaignEvent;
            foreach ($campaignEvent->getCompareEvents() as $compareEvent) {
                $this->addSubQueriesLogic($this->campaignEvent->getConditionsType(), $compareEvent);
            }
        }

        $campaignEvent  = $campaignEvents->first()->getCampaignEvent()->convertToArray();
        $addConditions = [];
        $sum = ArrayHelper::getValue('sum', $campaignEvent['properties']);
        if ($sum) {
            if (!empty($sum['field'])) {
                list($formId, $fieldAlias) = explode('|',$sum['field']);
                $subQuery = $this->getSubQuery($formId);
                $expr = $sum['expr'];
                $sumColumn = 'SUM('.$this->table.'.'.$fieldAlias.')';
                $q->addSelect($sumColumn.' as sumField');
                $addConditions[$this->table] = $subQuery->expr()->$expr($sumColumn, ':sumvalue');
                $q->setParameter('sumvalue', $sum['value']);
            }
        }


        if (!empty($this->subQueries)) {
            foreach ($this->subQueries as $table => $subQuery) {
                $orX = $subQuery->expr()->orX();
                foreach ($this->subQueriesConditions[$table] as $subQueriesConditions) {
                    $andX = $subQuery->expr()->andX();
                    foreach ($subQueriesConditions as $subQueriesCondition) {
                        $andX->add($subQueriesCondition);
                    }
                    $orX->add($andX);
                }
                if (isset($addConditions[$table])) {
                    $q->groupBy('s.lead_id');
                    $q->having($addConditions[$table]);
                }

                $subQuery->andWhere($orX);

                $q->innerJoin('s', sprintf("(%s)", $subQuery->getSQL()), $table, $table.'.submission_id = s.id');
                foreach ($subQuery->getParameters() as $key => $parameter) {
                    $q->setParameter($key, $parameter);
                }
            }
        }

        $this->logger->warning(
            sprintf("Complex query: %s", $this->getQuery($q))
        );
        $results = $q->execute()->fetchAll();
        if (isset($results[0]['sumField']) && !empty($sum['contactField'])) {
            $contact = $campaignEvents->first()->getContact();
            $this->leadModel->setFieldValues($contact, [$sum['contactField'] => $results[0]['sumField']]);
            $this->leadModel->saveEntity($contact);
        }
        return $results;
    }

    /**
     * @param QueryBuilder $query
     *
     * @return string
     */
    public function getQuery($query)
    {
        $q = $query->getSQL();
        $params             = $query->getParameters();

        foreach ($params as $name => $param) {
            if (is_array($param)) {
                $param = implode(',', $param);
            }
            $q = str_replace(":$name", "'$param'", $q);
        }

        return $q;
    }

    /**
     * @param string $table
     * @param string $tableAlias
     * @param int $formId
     *
     * @return QueryBuilder
     */
    private function getSubQuery(int $formId)
    {
        /** @var Form $form */
        $form = $this->form = $this->formModel->getEntity($formId);
        if (!$form) {
            return;
        }
        $this->table      = $this->submissionModel->getRepository()->getResultsTableName($formId, $form->getAlias());
        $this->tableAlias = 'alias'.md5($this->table);

        if (!isset($this->subQueries[$this->table])) {
            $subQuery = $this->entityManager->getConnection()->createQueryBuilder();

            $subQuery->select('*')
                ->from($this->table, $this->tableAlias);
            $subQuery->where(
                $subQuery->expr()->andX(
                    $subQuery->expr()->eq($this->tableAlias.'.form_id', ':formId')
                )
            )
                ->setParameter('formId', $formId);
            $this->subQueries[$this->table] = $subQuery;
        } else {
            $subQuery = $this->subQueries[$this->table];
        }

        return $subQuery;
    }

    /**
     * @param string       $conditionsType
     * @param CompareEvent $compareEvent
     */
    private function addSubQueriesLogic($conditionsType, CompareEvent $compareEvent)
    {
        $formId = $compareEvent->getProperties()->getFormId();
        $config = $compareEvent->getProperties()->getProperties();

        $subQuery = $this->getSubQuery($formId);

        $table = $this->table;
        $tableAlias = $this->tableAlias;
        $form = $this->form;

        $subQueryConditions = $subQuery->expr()->andX();

        $operatorExpr   = $compareEvent->getProperties()->getExpr($this->formModel->getFilterExpressionFunctions());
        $field          = $compareEvent->getProperties()->getFieldAlias();
        $value          = $compareEvent->getProperties()->getValue();
        $valueParameter = 'value'.md5($field.$tableAlias.$value);
        if ($compareEvent->getProperties()->isCustomDateCondition()) {
            $value = $this->formTabDateHelper->getDateFromConfig($compareEvent->getProperties()->getProperties());
        }

        // Modify operator
        switch ($operatorExpr) {
            case 'like':
            case 'notLike':
                $value = strpos($value, '%') === false ? '%'.$value.'%' : $value;
                break;
            case 'startsWith':
                $operatorExpr = 'like';
                $value        = $value.'%';
                break;
            case 'endsWith':
                $operatorExpr = 'like';
                $value        = '%'.$value;
                break;
            case 'contains':
                $operatorExpr = 'like';
                $value        = '%'.$value.'%';
                break;
        }

        if ($operatorExpr === 'anniversary') {
            $subQueryConditions->add(
                $subQuery->expr()->andX(
                    $subQuery->expr()->eq("MONTH($tableAlias.$field)", ':month'),
                    $subQuery->expr()->eq("DAY($tableAlias.$field)", ':day')
                )
            );
            $subQuery->setParameter('month', $value->format('m'));
            $subQuery->setParameter('day', $value->format('d'));
        } elseif ($operatorExpr === 'date') {
            $expr = 'eq';
            if (!empty($config['expr'])) {
                $expr = $config['expr'];
            }
            $subQueryConditions->add($subQuery->expr()->$expr($tableAlias.'.'.$field, ':'.$valueParameter));
            $subQuery->setParameter($valueParameter, $value->format('Y-m-d'));
        } else {
            switch (FormTabHelper::getFieldTypeFromFormByAlias($form, $field)) {
                case 'boolean':
                case 'number':
                    $subQueryConditions->add($subQuery->expr()->$operatorExpr($tableAlias.'.'.$field, $value));
                    break;
                default:
                    $subQueryConditions->add(
                        $subQuery->expr()->$operatorExpr($tableAlias.'.'.$field, ':'.$valueParameter)
                    );
                    $subQuery->setParameter($valueParameter, $value);
                    break;
            }
        }
        $this->subQueriesConditions[$table][$conditionsType][] = $subQueryConditions;
        $this->subQueries[$table] = $subQuery;
    }
}
