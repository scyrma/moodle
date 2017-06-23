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
                    window.console.log('miss');
                    cachedResponse = {
                        files: response.files
                            .filter(function(file) {
                                return file.filearea != 'draft';
                            })
                            .sort(function(a,b) {
                                return a.size < b.size;
                            })};
                    return cachedResponse;
                })
        );
    };

    return {
        getFilesBySize: getFilesBySize
    };
});
