define(['tool_fileslist/file_repository', 'core/templates'], function(FileRepository, Templates) {
    return {
        updateList: function(root) {
            var start = +root.attr('data-start');
            var limit = +root.attr('data-limit');

            return FileRepository
                .getFilesBySize()
                .then(function(response) {
                    root.attr('data-num-items', response.files.length);
                    return Templates.render(
                        'tool_fileslist/fileslist-items',
                        {
                            files: response.files
                                .slice(start, start + limit)
                                .map(function(file) {
                                    var index = Math.floor(Math.log(file.size) / Math.log(1024));
                                    file.sizeHumanReadable = Math.round((file.size/Math.pow(1024, index))*100)/100
                                        + ' ' + ['B', 'KB', 'MB', 'GB'][index];

                                    return file;
                                })
                        }
                    );
                })
                .done(function(html, js) {
                    root.attr('data-start', start + limit);
                    Templates.replaceNodeContents(root.find('tbody'), html, js);
                });
        }
    };
});
