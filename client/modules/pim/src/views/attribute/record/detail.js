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

Espo.define('pim:views/attribute/record/detail', 'views/record/detail-tree',
    Dep => Dep.extend({

        sideView: "pim:views/attribute/record/detail-side",

        bottomView: 'pim:views/attribute/record/detail-bottom',

        delete: function () {
            Espo.TreoUi.confirmWithBody('', {
                confirmText: this.translate('Remove'),
                cancelText: this.translate('Cancel'),
                body: this.getBodyHtml()
            }, function () {
                this.trigger('before:delete');
                this.trigger('delete');

                this.notify('Removing...');

                var collection = this.model.collection;

                var self = this;
                this.model.destroy({
                    wait: true,
                    error: function () {
                        this.notify('Error occured!', 'error');
                    }.bind(this),
                    success: function () {
                        if (collection) {
                            if (collection.total > 0) {
                                collection.total--;
                            }
                        }

                        this.clearFilters();

                        this.notify('Removed', 'success');
                        this.trigger('after:delete');
                        this.exit('delete');
                    }.bind(this),
                });
            }, this);
        },

        getBodyHtml() {
            return '' +
                '<div class="row">' +
                '<div class="col-xs-12">' +
                '<span class="confirm-message">' + this.translate('removeAttribute(s)', 'messages', 'Attribute') + '</span>' +
                '</div>' +
                '</div>';
        },

        clearFilters() {
            var presetFilters = this.getPreferences().get('presetFilters') || {};
            if (!('Product' in presetFilters)) {
                presetFilters['Product'] = [];
            }

            presetFilters['Product'].forEach(function (item, index, obj) {
                for (let filterField in item.data) {
                    let name = filterField.split('-')[0];

                    if (name === this.model.id) {
                        delete obj[index].data[filterField]
                    }
                }
            }, this);
            presetFilters['Product'] = presetFilters['Product'].filter(item => Object.keys(item.data).length > 0);

            this.getPreferences().set('presetFilters', presetFilters);
            this.getPreferences().save({patch: true});
            this.getPreferences().trigger('update');
            let filters = this.getStorage().get('listSearch', 'Product');
            if (filters && filters.advanced) {
                for (let filter in filters.advanced) {
                    let name = filter.split('-')[0];

                    if (name === this.id) {
                        delete filters.advanced[filter]
                    }
                }

                if (filters.presetName && !presetFilters['Product'].includes(filters.presetName)) {
                    filters.presetName = null
                }

                this.getStorage().set('listSearch', 'Product', filters);
            }
        }
    })
);