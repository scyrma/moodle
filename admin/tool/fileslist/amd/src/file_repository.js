define(['jquery', 'core/ajax'], function($, Ajax) {
    var cachedResponse;

    var getFilesBySize = function() {
        return $.when(
            cachedResponse ||
                Ajax.call([
                    {
                        methodname: 'tool_fileslist_get_files_list',
                        args: []
                    }
                ])[0].then(function(response) {
                    cachedResponse = {
                        files: response.files
                            .filter(function(file) {
                                return file.filearea != 'draft';
                            })
                    };
                    return cachedResponse;
                })
        );
    };

    return {
        getFilesBySize: getFilesBySize
    };
});
