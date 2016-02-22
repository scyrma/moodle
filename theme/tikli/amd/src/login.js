define(['jquery', 'core/str'], function($, str) {
    return {
        init: function() {
            strings = [
                {
                    key: 'username',
                    component: 'core',
                },
                {
                    key: 'password',
                    component: 'core',
                }
            ];

            str.get_strings(strings).done(function(results) {
                $('#username').attr('placeholder', results[0]);
                $('#password').attr('placeholder', results[1]);
            });
        }
    }
});
