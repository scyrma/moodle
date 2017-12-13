require(['core/str', 'jquery', 'core/config'], function(str, $, config) {
    var checkFunction = function() {
        var ads = $('#moodlecloud_ad');
        var ad_context = ads.attr('data-upsell-context');
        var sso_link = ads.attr('data-portal-sso');
        if (ads.length && ads.height() === 0 && !ads.data('notified') && typeof ad_context !== 'undefined') {
            YUI().use('moodle-core-notification-alert', function() {
                str.get_strings([
                    {
                        key: 'adunblock_title',
                        component: 'theme_boost'
                    },
                    {
                        key: 'adunblock_message_' + ad_context,
                        component: 'theme_boost',
                        param: {sso_link: sso_link},
                    }
                ]).done(function(strings) {
                    new M.core.alert({
                        'title': strings[0],
                        message: strings[1],
                        width: '500px',
                        extraClasses: ['moodlecloud-adblocker-modal']
                    });

                    var settings = {
                        sesskey: config.sesskey
                    };
                    $.post(config.wwwroot + '/theme/boost/adsblocked.php', settings);
                    ads.data('notified', true);
                });
            });
        }
    };

    // Defer the call to the adunblock check by five seconds to account for
    // some network latency in serving the ads.
    setTimeout(checkFunction, 5000);
});
