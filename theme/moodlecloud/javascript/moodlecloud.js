require(['core/str', 'jquery', 'core/config'], function(str, $, config) {
    $('#portal-link-container a').click(function(e) {
        if ("ga" in window) {
            // we have multiple trackers, one for region and one for global, so we need to iterate over each
            // tracker and send the event data to each
            trackers = ga.getAll();
            $.each(trackers, function(i, tracker) {
                if (tracker) {
                    tracker.send('event', 'SSO Tab', 'Click', '', {
                        transport: 'beacon'
                    });
                }
            });
        }
    });
});
