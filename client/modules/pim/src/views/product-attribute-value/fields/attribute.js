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

Espo.define('pim:views/product-attribute-value/fields/attribute', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList: ['notLinkedWithProductAttributeValue', 'fromAttributesTab'],

        listTemplate: 'pim:product-attribute-value/fields/attribute',

        boolFilterData: {
            notLinkedWithProductAttributeValue() {
                return {
                    productId: this.model.get('productId'),
                    channelId: this.model.get('channelId')
                };
            },
            fromAttributesTab() {
                if (!this.model.get('productId')) {
                    return;
                }
                return {
                    tabId: this.model.tabId ? this.model.tabId : null
                };
            }
        },

        createDisabled: true,

        setup() {
            this.mandatorySelectAttributeList = ['type', 'isMultilang', 'defaultScope', 'defaultChannelId', 'defaultChannelName', 'isRequired'];
            if (this.model.get('attributeTooltip')) {
                var $a;
                this.once('after:render', function () {
                    $a = $('<a href="javascript:" class="text-muted field-info"><span class="fas fa-info-circle"></span></a>');
                    var $label = this.$el.find('a[data-tooltip="'+this.model.get('attributeId')+'"]');
                    $label.append(' ');
                    $label.append($a);
                    $a.popover({
                        placement: 'bottom',
                        container: 'body',
                        html: true,
                        content: this.model.get('attributeTooltip').replace(/\n/g, "<br />"),
                        trigger: 'click',
                    }).on('shown.bs.popover', function () {
                        $('body').one('click', function () {
                            $a.popover('hide');
                        });
                    });
                }, this);
                this.on('remove', function () {
                    if ($a) {
                        $a.popover('destroy')
                    }
                }, this);
            }


            Dep.prototype.setup.call(this);
        },

        select(model) {
            this.setAttributeFieldsToModel(model);

            Dep.prototype.select.call(this, model);
            this.model.trigger('change:attribute', model);
        },

        setAttributeFieldsToModel(model) {
            let attributes = {
                attributeType: model.get('type'),
                attributeExtensibleEnumId: model.get('extensibleEnumId'),
                attributeMeasureId: model.get('measureId'),
                amountOfDigitsAfterComma: model.get('amountOfDigitsAfterComma'),
                attributeIsMultilang: model.get('isMultilang'),
                defaultScope: model.get('defaultScope'),
                defaultChannelId: model.get('defaultChannelId'),
                defaultChannelName: model.get('defaultChannelName'),
                isRequired: model.get('isRequired')
            };
            this.model.set(attributes);
        },

        data() {
            let data = Dep.prototype.data.call(this);
            let attributeData = this.model.get('data') || {};

            if ('title' in attributeData) {
                data.titleValue = attributeData.title;
            } else {
                data.titleValue = data.nameValue;
            }

            return data;
        },

        clearLink() {
            this.unsetAttributeFieldsInModel();

            Dep.prototype.clearLink.call(this);
        },

        unsetAttributeFieldsInModel() {
            ['attributeType', 'attributeIsMultilang'].forEach(field => this.model.unset(field));
        }

    })
);

