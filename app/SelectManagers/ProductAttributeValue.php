<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
use Pim\Core\SelectManagers\AbstractSelectManager;
use Espo\Core\Utils\Util;

class ProductAttributeValue extends AbstractSelectManager
{
    protected array $filterLanguages = [];
    protected array $filterScopes = [];

    public static function createLanguagePrismBoolFilterName(string $language): string
    {
        return 'prismVia' . ucfirst(Util::toCamelCase(strtolower($language)));
    }

    public static function createScopePrismBoolFilterName(string $id): string
    {
        return 'prismViaScope_' . $id;
    }

    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        // clear filter languages
        $this->filterLanguages = [];

        if (isset($params['where']) && is_array($params['where'])) {
            $pushBoolAttributeType = false;
            foreach ($params['where'] as $k => $v) {
                if ($v['value'] === 'onlyTabAttributes' && isset($v['data']['onlyTabAttributes'])) {
                    $onlyTabAttributes = true;
                    $tabId = $v['data']['onlyTabAttributes'];
                    if (empty($tabId) || $tabId === 'null') {
                        $tabId = null;
                    }
                    unset($params['where'][$k]);
                }
                if (!empty($v['attribute']) && $v['attribute'] === 'boolValue') {
                    $pushBoolAttributeType = true;
                }
            }
            $params['where'] = array_values($params['where']);

            if ($pushBoolAttributeType) {
                $params['where'][] = [
                    "type"      => "equals",
                    "attribute" => "attributeType",
                    "value"     => "bool"
                ];
            }
        }

        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);

        if (!isset($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }

        $language = \Pim\Services\ProductAttributeValue::getLanguagePrism();
        if (!empty($language)) {
            $languages = ['main'];
            if ($this->getConfig()->get('isMultilangActive')) {
                $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
            }
            if (!in_array($language, $languages)) {
                throw new BadRequest('No such language is available.');
            }
            $selectParams['customWhere'] .= " AND product_attribute_value.language IN ('main','$language')";
        }

        if (!empty($onlyTabAttributes)) {
            if (empty($tabId)) {
                $selectParams['customWhere'] .= " AND product_attribute_value.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id IS NULL AND deleted=0)";
            } else {
                $tabId = $this->getEntityManager()->getPDO()->quote($tabId);
                $selectParams['customWhere'] .= " AND product_attribute_value.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id=$tabId AND deleted=0)";
            }
        }

        $this->applyLanguageBoolFilters($params, $selectParams);
        $this->applyScopeBoolFilters($params, $selectParams);

        return $selectParams;
    }

    /**
     * @inheritDoc
     */
    public function applyAdditional(array &$result, array $params)
    {
        if ($this->isSubQuery) {
            return;
        }

        $additionalSelectColumns = [
            'attributeGroupId'   => 'ag1.id',
            'attributeGroupName' => 'ag1.name'
        ];

        $result['customJoin'] .= " LEFT JOIN attribute AS a1 ON a1.id=product_attribute_value.attribute_id AND a1.deleted=0";
        $result['customJoin'] .= " LEFT JOIN attribute_group AS ag1 ON ag1.id=a1.attribute_group_id AND ag1.deleted=0";

        foreach ($additionalSelectColumns as $alias => $sql) {
            $result['additionalSelectColumns'][$sql] = $alias;
        }
    }

    /**
     * @inheritDoc
     */
    protected function accessOnlyOwn(&$result)
    {
        $d['createdById'] = $this->getUser()->id;
        $d['ownerUserId'] = $this->getUser()->id;
        $d['assignedUserId'] = $this->getUser()->id;

        $result['whereClause'][] = array(
            'OR' => $d
        );
    }

    protected function boolFilterLinkedWithAttributeGroup(array &$result): void
    {
        $data = (array)$this->getSelectCondition('linkedWithAttributeGroup');

        if (isset($data['productId'])) {
            $attributes = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['id'])
                ->distinct()
                ->join('attribute')
                ->where(
                    [
                        'productId'                  => $data['productId'],
                        'attribute.attributeGroupId' => ($data['attributeGroupId'] != '') ? $data['attributeGroupId'] : null
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id' => array_column($attributes, 'id')
            ];
        }
    }

    public function applyBoolFilter($filterName, &$result)
    {
        if (self::createLanguagePrismBoolFilterName('main') === $filterName) {
            $this->filterLanguages[] = 'main';
        }
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                if (self::createLanguagePrismBoolFilterName($language) === $filterName) {
                    $this->filterLanguages[] = $language;
                }
            }
        }

        if (self::createScopePrismBoolFilterName('global') === $filterName) {
            $this->filterScopes[] = 'global';
        }

        foreach ($this->getMetadata()->get(['clientDefs', 'ProductAttributeValue', 'channels'], []) as $channel) {
            if (self::createScopePrismBoolFilterName($channel['id']) === $filterName) {
                $this->filterScopes[] = $channel['id'];
            }
        }

        parent::applyBoolFilter($filterName, $result);
    }

    public function applyLanguageBoolFilters($params, &$selectParams)
    {
        if (empty($this->filterLanguages)) {
            return;
        }

        $languages = implode("','", $this->filterLanguages);

        $selectParams['customWhere'] .= " AND (product_attribute_value.language IN ('$languages') OR product_attribute_value.attribute_id IN (SELECT id FROM attribute WHERE deleted=0 AND is_multilang=0))";
    }

    public function applyScopeBoolFilters($params, &$selectParams)
    {
        if (empty($this->filterScopes)) {
            return;
        }

        $channelsIds = [];
        foreach ($this->filterScopes as $channelId) {
            if ($channelId !== 'global') {
                $channelsIds[] = $channelId;
            }
        }
        $channelsIds[] = '';

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['channelId' => $channelsIds])
            ->order('channelId', true)
            ->find();

        $data = [];
        foreach ($pavs as $pav) {
            $hash = "{$pav->get('productId')}_{$pav->get('attributeId')}_{$pav->get('language')}";
            if (!isset($data[$hash])) {
                $data[$hash] = $pav->get('id');
            }
        }

        $ids = implode("','", array_values($data));

        $selectParams['customWhere'] .= " AND product_attribute_value.id IN ('$ids')";
    }
}
