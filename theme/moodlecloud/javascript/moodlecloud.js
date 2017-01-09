require(['core/str', 'jquery', 'core/config'], function(str, $, config) {
    var checkFunction = function() {
        var ads = $('#moodlecloud_ad');
        if (ads.length && ads.height() === 0 && !ads.data('notified')) {
            YUI().use('moodle-core-notification-alert', function() {
                str.get_strings([
                    {
                        key: 'adunblock_title',
                        component: 'theme_moodlecloud'
                    },
                    {
                        key: 'adunblock_message',
                        component: 'theme_moodlecloud'
                    }
                ]).done(function(strings) {
                    new M.core.alert({
                            'title': strings[0],
                            message: strings[1]
                        });

                        var settings = {
                            sesskey: config.sesskey
                        };
                        $.post(config.wwwroot + '/theme/moodlecloud/adsblocked.php', settings);
                        ads.data('notified', true);
                });
            });
        }
    };

    // Defer the call to the adunblock check by five seconds to account for
    // some network latency in serving the ads.
    setTimeout(checkFunction, 5000);
});
