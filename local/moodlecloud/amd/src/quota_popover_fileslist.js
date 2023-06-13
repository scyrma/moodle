define(['jquery', 'core/ajax', 'core/templates'], function($, Ajax, Templates) {
    var cachedResponse;

    return {
        /**
         * Update the files list.
         *
         * @param {jQuery} root The root element of the files list.
         * @return {Promise} Resolved when the elements are attached to the DOM
         */
        updateList: function(root) {
            return $.when(
                cachedResponse ||
                    Ajax.call([
                        {
                            methodname: 'local_moodlecloud_get_top_ten_file_types_by_size',
                            args: []
                        }
                    ])[0]
                    .then(function(response) {
                        cachedResponse =                             {
                            filetypes: response.filetypes.map(function(file) {
                                var index = Math.floor(Math.log(file.size) / Math.log(1024));
                                file.sizeHumanReadable = Math.round((file.size/Math.pow(1024, index))*100)/100
                                    + ' ' + ['B', 'KB', 'MB', 'GB'][index];
                                return file;
                            })
                        };

                        return cachedResponse;
                    })
            ).then(function(response) {
                return Templates.render('local_moodlecloud/quota_popover_file_type_list', response);
            }).done(function(html, js) {
                root.attr('data-fileslist-loaded', 'true');
                Templates.appendNodeContents(root.find('.items'), html, js);
            });
        }
    };
});
