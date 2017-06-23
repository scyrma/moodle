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
                                    var bytes = file.size;
                                    if(Math.abs(bytes) < 1024) {
                                        file.sizeHumanReadable = bytes + ' B';
                                        return file;
                                    }
                                    var units = ['kB','MB','GB','TB','PB','EB','ZB','YB'];
                                    var u = -1;
                                    do {
                                        bytes /= 1024;
                                        ++u;
                                    } while(Math.abs(bytes) >= 1024 && u < units.length - 1);
                                    file.sizeHumanReadable = bytes.toFixed(1)+' '+units[u];
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
